# SilexDoctrineHydrationProfile
Information about hydration of doctrine entities at the profiler in Silex

Provide information about doctrine hydration performance ([debesha/DoctrineProfileExtraBundle](https://github.com/debesha/DoctrineProfileExtraBundle)) for Silex.
Entity Manger provided by [dflydev/doctrine-orm-service-provider](https://github.com/dflydev/dflydev-doctrine-orm-service-provider)

# Installation

    composer require --dev bi0r0b0t/silex-doctrine-hydration-profiler "~0.1@dev"
    
# Registering
  
    $app->register(new SilexDoctrineHydrationProfile\SilexDoctrineHydrationProfileProvider());