<?php

declare(strict_types=1);

namespace Lits\Action;

use Lits\Action;
use Lits\Config\FormConfig;
use Lits\Config\LocationConfig;
use Lits\Exception\FailedSendingException;
use Lits\Exception\InvalidConfigException;
use Lits\Exception\InvalidDataException;
use Lits\Mail;
use Lits\Service\ActionService;
use Safe\DateTime;
use Safe\Exceptions\DatetimeException;
use Slim\Exception\HttpInternalServerErrorException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class IndexAction extends Action
{
    public function __construct(ActionService $service, private Mail $mail)
    {
        parent::__construct($service);
    }

    /** @throws HttpInternalServerErrorException */
    public function action(): void
    {
        \assert($this->settings['form'] instanceof FormConfig);

        try {
            if ($this->settings['form']->locations === []) {
                throw new InvalidConfigException(
                    'A location must be specified',
                );
            }

            $this->render(
                $this->template(),
                ['post' => $this->session->get('post')],
            );

            $this->session->remove('post');
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                null,
                $exception,
            );
        }
    }

    /**
     * @param array<string, string> $data
     * @throws HttpInternalServerErrorException
     */
    public function post(
        ServerRequest $request,
        Response $response,
        array $data,
    ): Response {
        $this->setup($request, $response, $data);
        $this->redirect();

        $post = (array) $this->request->getParsedBody();
        $post['reserves'] = $this->reserves($post);

        try {
            $this->validateRows($post, 'instructors', ['name', 'email']);
            $this->validateRows($post, 'courses', ['number', 'title']);
            $this->validateDate($post, 'date_begin');
            $this->validateDate($post, 'date_end');
            $this->send($post);

            unset($post['instructors']);
            unset($post['courses']);
            unset($post['reserves']);
        } catch (InvalidDataException $exception) {
            $this->message('failure', $exception->getMessage());
        } catch (\Throwable $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not process posted data',
                $exception,
            );
        }

        $this->session->set('post', $post);

        return $this->response;
    }

    /**
     * @param array<mixed> $data
     * @throws InvalidDataException
     */
    private function email(array $data, string $key): ?string
    {
        if (!$this->string($data, $key)) {
            return null;
        }

        $value = \filter_var($data[$key], \FILTER_VALIDATE_EMAIL);

        if ($value === false) {
            throw new InvalidDataException('Email addresses must be valid');
        }

        return $value;
    }

    /**
     * @param array<mixed> $post
     * @throws InvalidDataException
     */
    private function location(array $post): LocationConfig
    {
        \assert($this->settings['form'] instanceof FormConfig);

        if (!isset($post['location'])) {
            throw new InvalidDataException('A location must be selected');
        }

        foreach ($this->settings['form']->locations as $location) {
            if ($location->title === $post['location']) {
                return $location;
            }
        }

        throw new InvalidDataException('A valid location must be selected');
    }

    /**
     * @param array<mixed> $post
     * @return array<mixed>
     */
    private function reserves(array $post): array
    {
        if (!isset($post['reserves']) || !\is_array($post['reserves'])) {
            return [];
        }

        return \array_filter(
            $post['reserves'],
            fn ($reserve): bool =>
                isset($reserve['title']) ||
                isset($reserve['author']) ||
                isset($reserve['number']),
        );
    }

    /**
     * @param array<mixed> $post
     * @throws HttpInternalServerErrorException
     * @throws InvalidDataException
     */
    private function send(array $post): void
    {
        \assert($this->settings['form'] instanceof FormConfig);

        $location = $this->location($post);

        $message = $this->mail->message()
            ->from($location->email)
            ->to($location->email)
            ->subject($this->settings['form']->subject)
            ->htmlTemplate('mail.html.twig')
            ->context(['post' => $post, 'location' => $location]);

        $email = $this->email($post, 'email');

        if (!\is_null($email)) {
            $message->addTo($email);
        }

        try {
            $this->mail->send($message);
        } catch (FailedSendingException $exception) {
            throw new HttpInternalServerErrorException(
                $this->request,
                'Could not send mail',
                $exception,
            );
        }

        $this->message(
            'success',
            'Your ' . $this->settings['form']->subject . ' has been sent. ' .
            $location->instructions . ' You may send another request below.',
        );
    }

    /** @param array<mixed> $data */
    private function string(array $data, string $key): bool
    {
        return isset($data[$key]) &&
            \is_string($data[$key]) &&
            \trim($data[$key]) !== '';
    }

    /**
     * @param array<mixed> $post
     * @throws InvalidDataException
     */
    private function validateArray(array $post, string $key): void
    {
        if (!isset($post[$key]) || !\is_array($post[$key])) {
            throw new InvalidDataException('You must specify ' . $key);
        }
    }

    /**
     * @param array<mixed> $post
     * @throws InvalidDataException
     */
    private function validateDate(array $post, string $key): void
    {
        if (!$this->string($post, $key)) {
            return;
        }

        try {
            DateTime::createFromFormat('Y-m-d', (string) $post[$key]);
        } catch (DatetimeException $exception) {
            throw new InvalidDataException(
                'Dates must be valid',
                0,
                $exception,
            );
        }
    }

    /**
     * @param array<mixed> $post
     * @param array<string> $fields
     * @throws InvalidDataException
     */
    private function validateRows(
        array $post,
        string $key,
        array $fields,
    ): void {
        $this->validateArray($post, $key);

        foreach ($post[$key] as $row) {
            if (!\is_array($row)) {
                continue;
            }

            foreach ($fields as $field) {
                $this->validateRowsField($key, $field, $row);
            }
        }
    }

    /**
     * @param array<mixed> $row
     * @throws InvalidDataException
     */
    private function validateRowsField(
        string $key,
        string $field,
        array $row,
    ): void {
        if (
            !$this->string($row, $field) ||
            ($field === 'email' && $this->email($row, $field) === null)
        ) {
            throw new InvalidDataException(
                'You must specify valid ' . $field . 's for all ' . $key,
            );
        }
    }
}
