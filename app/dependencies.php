<?php
/**
 * Application Dependencies
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

use Slim\Container;
use Slim\Csrf\Guard;
use Psr\Log\LoggerInterface;
use Slim\Flash\Messages;
use Swift_Mailer;
use Slim\Views\Twig;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

// Add CSRF guard middleware to the container.
$container[Guard::class] = function (Container $container) {
    $guard = new Guard;

    // Mark requests with failed CSRF, instead of displaying error.
    $guard->setFailureCallable(function (Request $req, Response $res, $next) {
        return $next($req->withAttribute('csrf_failed', true), $res);
    });

    return $guard;
};

// Add a PSR-3 compatible logger to the container.
$container[LoggerInterface::class] = function (Container $container) {
    // Create new monolog logger.
    $logger = new \Monolog\Logger('app');

    // If a log file was specified, add handler for that file to logger.
    if ($container['settings']['app']['log']) {
        // Create stream handler for the specified log path.
        $handler = new \Monolog\Handler\StreamHandler(
            $container['settings']['app']['log']
        );

        // Format the handler to only include stacktraces if in debug mode.
        $formatter = new \Monolog\Formatter\LineFormatter();
        $formatter->includeStacktraces($container['settings']['app']['debug']);
        $handler->setFormatter($formatter);

        // Add web information to handler, and add handler to logger.
        $handler->pushProcessor(new \Monolog\Processor\WebProcessor());
        $logger->pushHandler($handler);
    }

    return $logger;
};

// Add flash messages to the container.
$container[Messages::class] = function (Container $container) {
    return new Messages($_SESSION);
};

// Add Swift Mailer to the container.
$container[Swift_Mailer::class] = function (Container $container) {
    $transport = new \Swift_SmtpTransport(
        $container['settings']['smtp']['host'],
        $container['settings']['smtp']['port']
    );

    return new Swift_Mailer($transport);
};

// Add a Twig template processor to the container.
$container[Twig::class] = function (Container $container) {
    // Always search package's template directory.
    $paths = [dirname(__DIR__) . '/templates'];

    // If another template directory is specified, search it first.
    if (!empty($container['settings']['template']['path'])) {
        array_unshift($paths, $container['settings']['template']['path']);
    }

    // Define options for Twig.
    $options = [
        'auto_reload' => true,
        'cache' => dirname(__DIR__) . '/cache',
        'debug' => $container['settings']['app']['debug']
    ];

    // Create Twig view and make package settings available.
    $view = new Twig($paths, $options);
    $view['settings'] = $container['settings']->all();

    // Add Aura.Html helper to the view.
    $helperLocatorFactory = new \Aura\Html\HelperLocatorFactory();
    $view['helper'] = $helperLocatorFactory->newInstance();

    // Add hidden inputs for CSRF protection.
    $view['csrf_hidden'] = trim($view['helper']->input([
        'type' => 'hidden',
        'name' => $container[Guard::class]->getTokenNameKey(),
        'value' => $container[Guard::class]->getTokenName()
    ]));

    $view['csrf_hidden'] .= trim($view['helper']->input([
        'type' => 'hidden',
        'name' => $container[Guard::class]->getTokenValueKey(),
        'value' => $container[Guard::class]->getTokenValue()
    ]));

    // Add Slim extension to the view.
    $view->addExtension(new \Slim\Views\TwigExtension(
        $container['router'],
        $container['request']->getUri()
    ));

    return $view;
};
