<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 */

namespace SilexDoctrineHydrationProfile;


use Debesha\DoctrineProfileExtraBundle\DataCollector\HydrationDataCollector;
use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;
use Debesha\DoctrineProfileExtraBundle\ORM\LoggingConfiguration;
use Debesha\DoctrineProfileExtraBundle\ORM\LoggingEntityManager;
use Doctrine\Common\Persistence\Mapping\Driver\StaticPHPDriver;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Doctrine\ORM\Mapping\Driver\SimplifiedYamlDriver;
use Doctrine\ORM\Mapping\Driver\XmlDriver;
use Doctrine\ORM\Mapping\Driver\YamlDriver;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;

class SilexDoctrineHydrationProfileProvider implements ServiceProviderInterface {
    /**
     * Registers services on the given app.
     *
     * @param Application $app
     */
    public function register(Application $app) {
        $app["debesha.doctrine_extra_profiler.logger"] = $app->share(function() use ($app){
            return new HydrationLogger($app->offsetGet("orm.em"));
        });
        $app["debesha.doctrine_extra_profiler.data_collector"] = $app->share(function() use ($app){
            return new HydrationDataCollector(
                $app->offsetGet("debesha.doctrine_extra_profiler.logger")
            );
        });
        $app['orm.ems.config'] = $app->share(function($app) {
            $app['orm.ems.options.initializer']();

            $configs = new \Pimple();
            foreach ($app['orm.ems.options'] as $name => $options) {
                $config = new LoggingConfiguration();

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
                 * @var $chain \Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain
                 */
                $chain = $app['orm.mapping_driver_chain.locator']($name);
                foreach ((array) $options['mappings'] as $entity) {
                    if (!is_array($entity)) {
                        throw new \InvalidArgumentException(
                            "The 'orm.em.options' option 'mappings' should be an array of arrays."
                        );
                    }

                    if (!empty($entity['resources_namespace'])) {
                        $app['psr0_resource_locator']->findFirstDirectory($entity['resources_namespace']);
                    }

                    if (isset($entity['alias'])) {
                        $config->addEntityNamespace($entity['alias'], $entity['namespace']);
                    }

                    switch ($entity['type']) {
                        case 'annotation':
                            $useSimpleAnnotationReader =
                                isset($entity['use_simple_annotation_reader'])
                                    ? $entity['use_simple_annotation_reader']
                                    : true;
                            $driver = $config->newDefaultAnnotationDriver((array) $entity['path'], $useSimpleAnnotationReader);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'yml':
                            $driver = new YamlDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'simple_yml':
                            $driver = new SimplifiedYamlDriver(array($entity['path'] => $entity['namespace']));
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'xml':
                            $driver = new XmlDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'simple_xml':
                            $driver = new SimplifiedXmlDriver(array($entity['path'] => $entity['namespace']));
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        case 'php':
                            $driver = new StaticPHPDriver($entity['path']);
                            $chain->addDriver($driver, $entity['namespace']);
                            break;
                        default:
                            throw new \InvalidArgumentException(sprintf('"%s" is not a recognized driver', $entity['type']));
                    }
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

        $app['orm.ems'] = $app->share(function(Application $app) {
            $app['orm.ems.options.initializer']();

            $ems = new \Pimple();
            foreach ($app['orm.ems.options'] as $name => $options) {
                if ($app['orm.ems.default'] === $name) {
                    // we use shortcuts here in case the default has been overridden
                    $config = $app['orm.em.config'];
                } else {
                    $config = $app['orm.ems.config'][$name];
                }

                $ems[$name] = $app->share(function() use ($app, $options, $config) {
                    return LoggingEntityManager::create(
                        $app['dbs'][$options['connection']],
                        $config,
                        $app['dbs.event_manager'][$options['connection']]
                    );
                });
            }

            return $ems;
        });

    }

    /**
     * Bootstraps the application.
     *
     * @param Application $app
     */
    public function boot(Application $app) {
        $collectors  = $app["data_collectors"];
        $templates   = $app["data_collector.templates"];
        $templates[] = array("hydrations", "@DebeshaDoctrineProfileExtraBundle/Collector/hydrations.html.twig");
        $options     = array('is_safe' => array('html'));
        $callable    = function($controller, $attributes = array(), $query = array()) {
            return new ControllerReference($controller, $attributes, $query);
        };
        $collectors["hydrations"] = $app->share(function($app) {
            return $app["debesha.doctrine_extra_profiler.data_collector"];
        });

        $app["data_collectors"]          = $collectors;
        $app["data_collector.templates"] = $templates;

        $app["twig"]->addFunction(new \Twig_SimpleFunction("controller", $callable, $options));
        $app['twig.loader.filesystem']->addPath(dirname(__FILE__).DIRECTORY_SEPARATOR."Views", "DebeshaDoctrineProfileExtraBundle");
    }

}