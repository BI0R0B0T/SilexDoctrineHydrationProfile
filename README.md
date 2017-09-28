# SilexDoctrineHydrationProfile
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/BI0R0B0T/SilexDoctrineHydrationProfile/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/BI0R0B0T/SilexDoctrineHydrationProfile/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/BI0R0B0T/SilexDoctrineHydrationProfile/badges/build.png?b=master)](https://scrutinizer-ci.com/g/BI0R0B0T/SilexDoctrineHydrationProfile/build-status/master)

Information about hydration of doctrine entities at the profiler in Silex

Provide information about doctrine hydration performance ([debesha/DoctrineProfileExtraBundle](https://github.com/debesha/DoctrineProfileExtraBundle)) for Silex.
Entity Manger provided by [dflydev/doctrine-orm-service-provider](https://github.com/dflydev/dflydev-doctrine-orm-service-provider)

# Installation

## Silex 2.0

    composer require --dev bi0r0b0t/silex-doctrine-hydration-profiler "~1.0"

## Silex 1.x

    composer require --dev bi0r0b0t/silex-doctrine-hydration-profiler "~0.1"

# Registering
  
    $app->register(new SilexDoctrineHydrationProfile\SilexDoctrineHydrationProfileProvider());