<?php

namespace OpiloClientTest\Integration;

use GuzzleHttp\ClientInterface;
use OpiloClient\Configs\Account;
use OpiloClient\Configs\ConnectionConfig;
use OpiloClient\Request\IncomingSMS;
use OpiloClient\Request\OutgoingSMS;
use OpiloClient\Response\CheckStatusResponse;
use OpiloClient\Response\Credit;
use OpiloClient\Response\Inbox;
use OpiloClient\Response\SMSId;
use OpiloClient\Response\Status;
use OpiloClient\V2\HttpClient6;
use PHPUnit_Framework_TestCase;

class HttpClient6BasicTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var HttpClient6
     */
    private $client;

    private $guzzleVersion;

    public function setUp()
    {
        parent::setUp();
        $this->client = new HttpClient6(new ConnectionConfig(getenv('OPILO_URL')), new Account(getenv('OPILO_USERNAME'), getenv('OPILO_PASSWORD')));
        $this->guzzleVersion = (string)ClientInterface::VERSION[0];
    }

    public function testGetCredit()
    {
        if ($this->guzzleVersion !== '6') {
            return;
        }
        $credit = $this->client->getCredit();
        $this->assertInstanceOf(Credit::class, $credit);
        $this->assertTrue(is_numeric($credit->getSmsPageCount()));
    }

    public function testSendSingleSMS()
    {
        if ($this->guzzleVersion !== '6') {
            return;
        }
        $initCredit = $this->client->getCredit()->getSmsPageCount();
        $message = new OutgoingSMS(getenv('PANEL_LINE'), getenv('DESTINATION'), 'V2::testSendSingleSMS()', null);
        $response = $this->client->sendSMS($message);
        $this->assertCount(1, $response);
        $this->assertInstanceOf(SMSId::class, $response[0]);
        $status = $this->client->checkStatus($response[0]->getId());
        $this->assertInstanceOf(CheckStatusResponse::class, $status);
        $status = $status->getStatusArray();
        $this->assertCount(1, $status);
        $this->assertInstanceOf(Status::class, $status[0]);
        $finalCredit = $this->client->getCredit()->getSmsPageCount();
        $this->assertLessThanOrEqual(1, $initCredit - $finalCredit);
    }

    public function testSendMultipleSMS()
    {
        if ($this->guzzleVersion !== '6') {
            return;
        }
        $initCredit = $this->client->getCredit()->getSmsPageCount();
        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = new OutgoingSMS(getenv('PANEL_LINE'), getenv('DESTINATION'), 'V2::testSendMultipleSMS' . "($i)", $i, new \DateTime("+$i Minutes"));
        }

        $response = $this->client->sendSMS($messages);
        $this->assertCount(10, $response);
        $ids = [];
        foreach ($response as $id) {
            $this->assertInstanceOf(SMSId::class, $id);
            $ids[] = $id->getId();
        }

        $status = $this->client->checkStatus($ids);
        $this->assertInstanceOf(CheckStatusResponse::class, $status);
        $status = $status->getStatusArray();
        $this->assertCount(10, $status);
        foreach ($status as $stat) {
            $this->assertInstanceOf(Status::class, $stat);
        }

        $finalCredit = $this->client->getCredit()->getSmsPageCount();
        $this->assertLessThanOrEqual(10, $initCredit - $finalCredit);
    }

    public function testCheckInbox()
    {
        if ($this->guzzleVersion !== '6') {
            return;
        }
        $response = $this->client->checkInbox(0);
        $this->assertInstanceOf(Inbox::class, $response);
        $response = $response->getMessages();
        $this->assertTrue(is_array($response));
        $this->assertLessThanOrEqual(Inbox::PAGE_LIMIT, count($response));
        foreach ($response as $sms) {
            $this->assertInstanceOf(IncomingSMS::class, $sms);
        }
    }
}