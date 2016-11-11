<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 */

namespace SilexDoctrineHydrationProfile;

use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;
use Doctrine\DBAL\Types\Type;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

class SilexDoctrineHydrationProfileProvider implements ServiceProviderInterface
{

    const ORM_EM_OPTIONS = 'orm.em.options';

    /**
     * Registers services on the given app.
     *
     * @param Application $app
     */
    public function register(Application $app)
    {
        $app["debesha.doctrine_extra_profiler.logger"] = $app->share(function () use ($app) {
            return new HydrationLogger($app->offsetGet("orm.em"));
        });
        $app['debesha.class.hydrationDataCollector'] = 'SilexDoctrineHydrationProfile\Fix\DataCollector';
        $app["debesha.doctrine_extra_profiler.data_collector"] = $app->share(function () use ($app) {
            $class = $app['debesha.class.hydrationDataCollector'];
            return new $class(
                $app->offsetGet("debesha.doctrine_extra_profiler.logger")
            );
        });

        if ($app->offsetExists(self::ORM_EM_OPTIONS)) {
            $options = $app->offsetGet(self::ORM_EM_OPTIONS);
        } else {
            $options = array();
        }

        $overwrite = array(
            'class.configuration'     => 'SilexDoctrineHydrationProfile\Fix\LoggingConfiguration',
            'class.entityManager'     => 'Debesha\DoctrineProfileExtraBundle\ORM\LoggingEntityManager',
            'class.driver.yml'        => 'Doctrine\ORM\Mapping\Driver\YamlDriver',
            'class.driver.simple_yml' => 'Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver',
            'class.driver.xml'        => 'Doctrine\ORM\Mapping\Driver\XmlDriver',
            'class.driver.simple_xml' => 'Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver',
            'class.driver.php'        => 'Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver',
        );

        foreach ($overwrite as $key => $className) {
            if (!isset($options[$key])) {
                $options[$key] = $className;
            }
        }
        $app[self::ORM_EM_OPTIONS] = $options;

        $app['orm.ems'] = $app->share(function($app) {
            /**
             * @var \Pimple $app
             */
            $app['orm.ems.options.initializer']();

            $ems = new \Pimple();
            foreach ($app['orm.ems.options'] as $name => $options) {
                if ($app['orm.ems.default'] === $name) {
                    // we use shortcuts here in case the default has been overridden
                    $config = $app['orm.em.config'];
                } else {
                    $config = $app['orm.ems.config'][$name];
                }

                $ems[$name] = $app->share(function () use ($app, $options, $config) {
                    /**
                     * @var $entityManagerClassName \Doctrine\ORM\EntityManager
                     */
                    $entityManagerClassName = $options['class.entityManager'];
                    return $entityManagerClassName::create(
                        $app['dbs'][$options['connection']],
                        $config,
                        $app['dbs.event_manager'][$options['connection']]
                    );
                });
            }
            return $ems;
        });

        $app['orm.ems.config'] = $app->share(function(\Pimple $app) {
            $app['orm.ems.options.initializer']();

            $configs = new \Pimple();
            foreach ($app['orm.ems.options'] as $name => $options) {
                /**
                 * @var $config \Doctrine\ORM\Configuration
                 */
                $configurationClassName = $options['class.configuration'];
                $config = new $configurationClassName;

                $app['orm.cache.configurer']($name, $config, $options);

                $config->setProxyDir($app['orm.proxies_dir']);
                $config->setProxyNamespace($app['orm.proxies_namespace']);
                $config->setAutoGenerateProxyClasses($app['orm.auto_generate_proxies']);

                $config->setCustomStringFunctions($app['orm.custom.functions.string']);
                $config->setCustomNumericFunctions($app['orm.custom.functions.numeric']);
                $config->setCustomDatetimeFunctions($app['orm.custom.functions.datetime']);
                $config->setCustomHydrationModes($app['orm.custom.hydration_modes']);

                $config->setClassMetadataFactoryName($app['orm.class_metadata_factory_name']);
                $config->setDefaultRepositoryClassName($app['orm.default_repository_class']);

                $config->setEntityListenerResolver($app['orm.entity_listener_resolver']);
                $config->setRepositoryFactory($app['orm.repository_factory']);

                $config->setNamingStrategy($app['orm.strategy.naming']);
                $config->setQuoteStrategy($app['orm.strategy.quote']);

                /**
                 * @var \Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain $chain
                 */
                $chain = $app['orm.mapping_driver_chain.locator']($name);
                foreach ((array) $options['mappings'] as $entity) {
                    if (!is_array($entity)) {
                        throw new \InvalidArgumentException(
                            "The 'orm.em.options' option 'mappings' should be an array of arrays."
                        );
                    }

                    if (!empty($entity['resources_namespace'])) {
                        if($app->offsetExists('psr0_resource_locator')) {
                            $entity['path'] = $app['psr0_resource_locator']->findFirstDirectory($entity['resources_namespace']);
                        } else {
                            throw new \InvalidArgumentException('Not exist psr0_resource_locator');
                        }
                    }

                    if (isset($entity['alias'])) {
                        $config->addEntityNamespace($entity['alias'], $entity['namespace']);
                    }

                    if('annotation' === $entity['type']){
                        $useSimpleAnnotationReader = isset($entity['use_simple_annotation_reader'])
                            ? $entity['use_simple_annotation_reader']
                            : true;
                        $driver =  $config->newDefaultAnnotationDriver((array) $entity['path'], $useSimpleAnnotationReader);
                    } else {
                        $driver = $app['orm.driver.factory']( $entity, $options );
                    }
                    $chain->addDriver($driver, $entity['namespace']);
                }
                $config->setMetadataDriverImpl($chain);

                foreach ((array) $options['types'] as $typeName => $typeClass) {
                    if (Type::hasType($typeName)) {
                        Type::overrideType($typeName, $typeClass);
                    } else {
                        Type::addType($typeName, $typeClass);
                    }
                }

                $configs[$name] = $config;
            }

            return $configs;
        });


        $app['orm.driver.factory'] = $app->share(function(){
            $simpleList = array(
                'simple_yml',
                'simple_xml',
            );
            return function ( $entity, $options ) use ($simpleList) {
                if (isset($options[ 'class.driver.' . $entity['type'] ])) {
                    $className = $options[ 'class.driver.' . $entity['type'] ];
                    if( in_array($entity['type'], $simpleList) ) {
                        $param = array($entity['path'] => $entity['namespace']);
                    } else {
                        $param = $entity['path'];
                    }
                    return new $className($param);
                }
                throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
            };
        });

    }

    /**
     * Bootstraps the application.
     *
     * @param Application $app
     */
    public function boot(Application $app)
    {
        $collectors  = $app["data_collectors"];
        $templates   = $app["data_collector.templates"];
        $templates[] = array("hydrations", "@DebeshaDoctrineProfileExtraBundle/Collector/hydrations.html.twig");
        $options     = array('is_safe' => array('html'));
        $callable    = function ($controller, $attributes = array(), $query = array()) {
            return new ControllerReference($controller, $attributes, $query);
        };
        $collectors["hydrations"] = $app->share(function ($app) {
            return $app["debesha.doctrine_extra_profiler.data_collector"];
        });

        $app["data_collectors"]          = $collectors;
        $app["data_collector.templates"] = $templates;

        $app["twig"]->addFunction(new \Twig_SimpleFunction("controller", $callable, $options));
        $app['twig.loader.filesystem']->addPath(dirname(__FILE__) . DIRECTORY_SEPARATOR . "Views", "DebeshaDoctrineProfileExtraBundle");
    }

}