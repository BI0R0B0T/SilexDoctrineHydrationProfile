<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 */

namespace SilexDoctrineHydrationProfile;


use Debesha\DoctrineProfileExtraBundle\DataCollector\HydrationDataCollector;
use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;
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
        $app["debesha.doctrine_extra_profiler.data_collector"] = $app->share(function () use ($app) {
            return new HydrationDataCollector(
                $app->offsetGet("debesha.doctrine_extra_profiler.logger")
            );
        });

        if ($app->offsetExists(self::ORM_EM_OPTIONS)) {
            $options = $app->offsetGet(self::ORM_EM_OPTIONS);
        } else {
            $options = array();
        }

        $classList = array(
            'class.configuration' => 'Debesha\DoctrineProfileExtraBundle\ORM\LoggingConfiguration',
            'class.entityManager' => 'Debesha\DoctrineProfileExtraBundle\ORM\LoggingEntityManager',
        );

        foreach ($classList as $key => $className) {
            if (!isset($options[$key])) {
                $options[$key] = $className;
            }
        }
        $app[self::ORM_EM_OPTIONS] = $options;
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