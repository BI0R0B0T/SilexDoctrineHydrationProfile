<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 * @date   11.11.2016 15:26
 */

namespace SilexDoctrineHydrationProfile\Fix;

use Debesha\DoctrineProfileExtraBundle\DataCollector\HydrationDataCollector;
use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;


class DataCollector extends HydrationDataCollector
{

    /**
     * @var HydrationLogger
     */
    private $hydrationLogger = array ();

    public function __construct(HydrationLogger $logger)
    {
        $this->hydrationLogger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {

        $this->data['hydrations'] = $this->hydrationLogger->hydrations;
    }
}