<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LightSms;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Vasilij Duško <vasilij@d4d.lt>
 */
final class LightSmsTransport extends AbstractTransport
{
    protected const HOST = 'www.lightsms.com/external/get/send.php';

    private $login;
    private $password;
    private $phone;
    /**
     * @var MessageInterface
     */
    private $message;

    private $errorCodes = [
        '000' => 'Service unavailable',
        '1' => 'Signature not specified',
        '2' => 'Login not specified',
        '3' => 'Text not specified',
        '4' => 'Phone number not specified',
        '5' => 'Sender not specified',
        '6' => 'Invalid signature',
        '7' => 'Invalid login',
        '8' => 'Invalid sender name',
        '9' => 'Sender name not registered',
        '10' => 'Sender name not approved',
        '11' => 'There are forbidden words in the text',
        '12' => 'Error in SMS sending',
        '13' => 'Phone number is in the blackist. SMS sending to this number is forbidden.',
        '14' => 'There are more than 50 numbers in the request',
        '15' => 'List not specified',
        '16' => 'Invalid phone number',
        '17' => 'SMS ID not specified',
        '18' => 'Status not obtained',
        '19' => 'Empty response',
        '20' => 'The number already exists',
        '21' => 'No name',
        '22' => 'Template already exists',
        '23' => 'Month not specifies (Format: YYYY-MM)',
        '24' => 'Timestamp not specified',
        '25' =>'Error in access to the list',
        '26' => 'There are no numbers in the list',
        '27' => 'No valid numbers',
        '28' => 'Date of start not specified (Format: YYYY-MM-DD)',
        '29' => 'Date of end not specified (Format: YYYY-MM-DD)',
        '30' => 'No date (format: YYYY-MM-DD)',
        '31' => 'Closing direction to the user',
        '32' => 'Not enough money',
        '33' => 'Phone number is not set',
        '34' => 'Phone is in stop list',
        '35' => 'Not enough money',
        '36' => 'Can not obtain information about phone',
        '37' => 'Base Id is not set',
        '38' => 'Phone number is already exist in this base',
        '39' => 'Phone number is not exist in this base',
    ];

    public function __construct(
        string $login,
        string $password,
        string $phone,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        $this->login = $login;
        $this->password = $password;
        $this->phone = $phone;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('lightsms://%s?phone=%s', $this->getEndpoint(), $this->phone);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && $this->phone === str_replace('+', '', $message->getPhone());
    }

    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof SmsMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, SmsMessage::class, \get_class($message)));
        }

        $this->message = $message;

        $signature = $this->getSignature();

        $endpoint = sprintf(
            'https://%s?login=%s&signature=%s&phone=%s&text=%s&sender=%s&timestamp=%s',
            $this->getEndpoint(),
            $this->login,
            $signature,
            str_replace('+', '', $message->getPhone()),
            $message->getSubject(),
            $this->phone,
            time()
        );


        $response = $this->client->request('GET', $endpoint);

        $content = $response->toArray(false);

        if (isset($content['error'])) {
            throw new TransportException('Unable to send the SMS: '.$this->errorCodes[$content['error']], $response);
        }
    }

    private function getSignature(): string
    {

        $params = [
            'timestamp' => time(),
            'login' => $this->login,
            'phone' => str_replace('+', '', $this->message->getPhone()),
            'sender' => $this->phone,
            'text' => $this->message->getSubject(),
        ];

        ksort($params);
        reset($params);

        return md5(implode($params) . $this->password);
    }
}
