<?php

namespace Skaisser\Tests\Sendy;

use Skaisser\Sendy\Sendy;
use PHPUnit\Framework\TestCase;

class SendyTest extends TestCase
{
    private $config = [
        'listId' => 'YOUR_LIST_ID',
        'installationUrl' => 'YOUR_URL',
        'apiKey' => 'API_KEY_HERE',
    ];

    public function testSimpleSubscribe(): void
    {
        $subscriber = new Sendy($this->config);

        $result = $subscriber->subscribe([
            'name' => 'Test',
            'email' => 'test@gmail.com',
        ]);

        $this->assertTrue($result['status']);
        $this->assertSame('Subscribed.', $result['message']);
    }

    public function testSubscribeASubscriberThatAlreadyExists(): void
    {
        $subscriber = new Sendy($this->config);

        $subscriber->subscribe([
            'name' => 'Test',
            'email' => 'test@gmail.com',
        ]);

        $result = $subscriber->subscribe([
            'name' => 'Test',
            'email' => 'test@gmail.com',
        ]);

        $this->assertTrue($result['status']);
        $this->assertSame('Already subscribed.', $result['message']);
    }

    public function testSimpleUnsubscribe(): void
    {
        $subscriber = new Sendy($this->config);

        $result = $subscriber->unsubscribe('test@gmail.com');
        $this->assertTrue($result['status']);
        $this->assertSame('Unsubscribed', $result['message']);
    }

    public function testUnsubscribeASubscriberThatNotExists(): void
    {
        $subscriber = new Sendy($this->config);

        $result = $subscriber->unsubscribe('unknown@gmail.com');

        // Assuming the API treats this as successful even if the email was not found
        $this->assertTrue($result['status']);
        $this->assertSame('Unsubscribed', $result['message']);
    }

    public function testCheckStatus(): void
    {
        $subscriber = new Sendy($this->config);

        $status1 = $subscriber->status('unknown@gmail.com');
        $this->assertSame('Email does not exist in list', $status1);

        $status2 = $subscriber->status('test@gmail.com');
        $this->assertSame('Unsubscribed', $status2);

        $status3 = $subscriber->status('test@gmail.com');
        $this->assertSame('Subscribed', $status3);
    }

    public function testUpdate(): void
    {
        $subscriber = new Sendy($this->config);
        $result = $subscriber->update('test@gmail.com', [
            'name' => 'New Test',
        ]);

        // This method uses `subscribe` to update data
        $this->assertTrue($result['status']);
        $this->assertSame('Already subscribed.', $result['message']);
    }

    public function testDelete(): void
    {
        $subscriber = new Sendy($this->config);
        $subscriber->subscribe([
            'name' => 'delete',
            'email' => 'delete@gmail.com',
        ]);
        $currentStatus = $subscriber->status('delete@gmail.com');
        $this->assertSame('Subscribed', $currentStatus);

        $deleteResult = $subscriber->delete('delete@gmail.com');
        $this->assertTrue($deleteResult['status']);

        $currentStatus = $subscriber->status('delete@gmail.com');
        $this->assertSame('Email does not exist in list', $currentStatus);
    }
}