<?php
/**
 * Application Handlers
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Handler;

use Slim\Container;
use Psr\Log\LoggerInterface;
use Slim\Views\Twig;

// Add our application's error handler to container.
$container['errorHandler'] = function (Container $container) {
    return new ErrorHandler(
        $container[LoggerInterface::class],
        $container[Twig::class],
        $container['settings']['app']['debug']
    );
};

// Add our application's not found handler to container.
$container['notFoundHandler'] = function (Container $container) {
    return new NotFoundHandler($container[Twig::class]);
};

// Add our application's method not allowed handler to container.
$container['notAllowedHandler'] = function (Container $container) {
    return new NotAllowedHandler($container[Twig::class]);
};
