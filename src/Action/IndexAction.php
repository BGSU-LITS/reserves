<?php
/**
 * Index Action Class
 * @author John Kloor <kloor@bgsu.edu>
 * @copyright 2017 Bowling Green State University Libraries
 * @license MIT
 */

namespace App\Action;

use App\Exception\RequestException;

use Slim\Flash\Messages;
use Slim\Views\Twig;
use Swift_Mailer;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * A class to be invoked for the form action.
 */
class IndexAction
{
    /**
     * Flash messenger.
     * @var Messages
     */
    private $flash;

    /**
     * View renderer.
     * @var Twig
     */
    private $view;

    /**
     * Email sender.
     * @var Swift_Mailer
     */
    private $mailer;

    /**
     * Locations to reserve from.
     * @var array
     */
    private $locations;

    /**
     * Construct the action with objects and configuration.
     * @param Messages $flash Flash messenger
     * @param Twig $view View renderer.
     * @param Swift_Mailer $mailer Email sender.
     * @param array $locations Locations to reserve from.
     */
    public function __construct(
        Messages $flash,
        Twig $view,
        Swift_Mailer $mailer,
        array $locations
    ) {
        $this->flash = $flash;
        $this->view = $view;
        $this->mailer = $mailer;
        $this->locations = $locations;
    }

    /**
     * Method called when class is invoked as an action.
     * @param Request $req The request for the action.
     * @param Response $res The response from the action.
     * @param array $args The arguments for the action.
     * @return Response The response from the action.
     */
    public function __invoke(Request $req, Response $res, array $args)
    {
        $args['messages'] = $this->messages();
        $args['locations'] = [];

        foreach ($this->locations as $key => $value) {
            $args['locations'][$key] = $value['title'];
        }

        if (!empty($args['messages'])) {
            $args['copyright'] = true;
        }

        if ($req->getMethod() === 'POST') {
            $keys = [
                'copyright',
                'instructors',
                'courses',
                'reserves',
                'location',
                'terms',
                'date_begin',
                'date_end',
                'additional',
                'email'
            ];

            foreach ($keys as $key) {
                $args[$key] = $req->getParam($key);
            }

            try {
                $this->validateCsrf($req);
                $this->validateRequest($args);
                $this->sendEmail($args);

                $this->flash->addMessage(
                    'success',
                    'Your course reserves registration has been sent.' .
                    ' ' . $this->locations[$args['location']]['instructions'] .
                    ' You may send another registration below.'
                );

                return $res->withStatus(302)->withHeader(
                    'Location',
                    $req->getUri()->getBasePath()
                );
            } catch (RequestException $exception) {
                $args['messages'][] = [
                    'level' => 'failure',
                    'message' => $exception->getMessage()
                ];
            }
        }

        foreach (['instructors', 'courses', 'reserves'] as $key) {
            if (empty($args[$key])) {
                $args[$key][] = false;
            }

            $args[$key][] = false;
        }

        // Render form template.
        return $this->view->render($res, 'index.html.twig', $args);
    }

    private function messages()
    {
        $result = [];

        foreach (['success', 'failure'] as $level) {
            $messages = $this->flash->getMessage($level);

            if (is_array($messages)) {
                foreach ($messages as $message) {
                    $result[] = [
                        'level' => $level,
                        'message' => $message
                    ];
                }
            }
        }

        return $result;
    }

    private function sendEmail(array $args)
    {
        foreach ($args['reserves'] as $key => $value) {
            if (empty($value['title']) &&
                empty($value['author']) &&
                empty($value['number'])) {
                unset($args['reserves'][$key]);
            }
        }

        $args['instructions'] =
            $this->locations[$args['location']]['instructions'];

        $messageFrom = $this->locations[$args['location']]['email'];
        $messageTo[] = $messageFrom;

        if (!empty($args['email'])) {
            $messageTo[] = $args['email'];
        }

        try {
            $message = $this->mailer->createMessage()
                ->setSubject('Course Reserves Registration')
                ->setFrom($messageFrom)
                ->setTo($messageTo)
                ->setBody(
                    $this->view->fetch('email.html.twig', $args),
                    'text/html'
                );

            if (!$this->mailer->send($message)) {
                throw new RequestException(
                    'Could not send email to the address specified.'
                );
            }
        } catch (\Swift_SwiftException $e) {
            throw new RequestException(
                'An unexpected error occurred. Please try again.'
            );
        }
    }

    private function validateCsrf(Request $req)
    {
        if ($req->getAttribute('csrf_failed')) {
            throw new RequestException(
                'Your request timed out. Please try again.'
            );
        }
    }

    private function validateRequest(array $args)
    {
        if (empty($args['copyright'])) {
            throw new RequestException(
                'You must review and complete the copyright section below.'
            );
        }

        $rows = [
            'instructors' => ['name', 'email'],
            'courses' => ['number', 'title']
        ];

        foreach ($rows as $key => $value) {
            $this->validateRow($key, $value, $args);
        }

        if (empty($this->locations[$args['location']])) {
            throw new RequestException(
                'You must specify a valid location.'
            );
        }

        foreach (['date_begin', 'date_end'] as $key) {
            if (!empty($args[$key])) {
                if (strtotime($args[$key]) < 1) {
                    throw new RequestException(
                        'You must specify valid dates.'
                    );
                }
            }
        }

        if (!empty($args['email'])) {
            if (!filter_var($args['email'], FILTER_VALIDATE_EMAIL)) {
                throw new RequestException(
                    'You must specify a valid confirmation email.'
                );
            }
        }
    }

    private function validateRow($name, array $required, array $args)
    {
        if (empty($args[$name])) {
            throw new RequestException(
                'You must specify ' . $name . '.'
            );
        }

        foreach ($args[$name] as $value) {
            foreach ($required as $key) {
                if (empty($value[$key])) {
                    throw new RequestException(
                        'You must specify ' . $key . 's for all ' . $name . '.'
                    );
                }
            }

            if (!empty($value['email'])) {
                if (!filter_var($value['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new RequestException(
                        'You must specify valid emails for all ' . $name . '.'
                    );
                }
            }
        }
    }
}
