<?php
declare(strict_types=1);

namespace AcMailer;

class Module
{
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    public function __invoke()
    {
        $moduleConfig = $this->getConfig();
        $moduleConfig['dependencies'] = $moduleConfig['service_manager'];
        unset($moduleConfig['service_manager']);

        return $moduleConfig;
    }
}
