<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 * @date   11.11.2016 15:26
 */

namespace SilexDoctrineHydrationProfile\Fix;

use Debesha\DoctrineProfileExtraBundle\DataCollector\HydrationDataCollector;
use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;


class FixHydrationDataCollector extends HydrationDataCollector
{

    /**
     * @var HydrationLogger
     */
    protected $hydrationLogger = array ();

    public function __construct(HydrationLogger $logger)
    {
        $this->hydrationLogger = $logger;
    }

}