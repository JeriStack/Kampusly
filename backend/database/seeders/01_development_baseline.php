<?php

declare(strict_types=1);

use Kampusly\Bootstrap\Config;
use Kampusly\Bootstrap\Container;
use Kampusly\Shared\Infrastructure\Database\DevelopmentBaselineSeeder;
use Kampusly\Shared\Infrastructure\Database\PdoConnectionFactory;

return static function (Container $container): array {
    /** @var Config $config */
    $config = $container->get(Config::class);
    /** @var PdoConnectionFactory $pdoFactory */
    $pdoFactory = $container->get(PdoConnectionFactory::class);

    $seeder = new DevelopmentBaselineSeeder(
        $pdoFactory->getConnection(),
        $config->getString('app.env', 'local')
    );

    return $seeder->run();
};

