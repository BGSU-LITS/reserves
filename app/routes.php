<?php
/**
 * Application Routes
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Action;

use Slim\Container;
use Slim\Flash\Messages;
use Swift_Mailer;
use Slim\Views\Twig;

// Index action.
$container[IndexAction::class] = function (Container $container) {
    return new IndexAction(
        $container[Messages::class],
        $container[Twig::class],
        $container[Swift_Mailer::class],
        $container['settings']['locations']
    );
};

$app->any('/', IndexAction::class);
