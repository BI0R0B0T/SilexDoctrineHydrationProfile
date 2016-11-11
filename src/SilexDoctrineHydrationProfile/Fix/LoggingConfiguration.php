<?php
/**
 * @author Dolgov_M <dolgov@bk.ru>
 * @date   11.11.2016 17:32
 */

namespace SilexDoctrineHydrationProfile\Fix;


use Debesha\DoctrineProfileExtraBundle\ORM\HydrationLogger;
use Doctrine\ORM\Configuration;

class LoggingConfiguration extends Configuration
{

    /**
     * Gets the hydration logger.
     *
     * @return HydrationLogger
     */
    public function getHydrationLogger()
    {
        return isset($this->_attributes['hydrationLogger'])
            ? $this->_attributes['hydrationLogger']
            : null;
    }

    /**
     * Sets the hydration logger.
     *
     * @param HydrationLogger $logger
     *
     * @return void
     */
    public function setHydrationLogger(HydrationLogger $logger)
    {
        $this->_attributes['hydrationLogger'] = $logger;
    }
}