<?php

namespace Skaisser\Sendy;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Class Sendy
 *
 * @package Skaisser\Sendy
 */
class Sendy
{
    protected $config;
    protected $installationUrl;
    protected $apiKey;
    protected $listId;
    protected Client $httpClient;

    /**
     * Sendy constructor.
     *
     * @param array $config
     *
     * @throws \Exception
     */
    public function __construct(array $config)
    {
        $this->setListId($config['listId'] ?? throw new \InvalidArgumentException('List ID is required.'));
        $this->setInstallationUrl($config['installationUrl'] ?? throw new \InvalidArgumentException('Installation URL is required.'));
        $this->setApiKey($config['apiKey'] ?? throw new \InvalidArgumentException('API Key is required.'));

        $this->httpClient = new Client([
            'base_uri' => $this->installationUrl,
            'timeout' => 5.0, // Set the timeout as needed
        ]);

        $this->checkProperties();
    }

    /**
     * @param string $installationUrl
     */
    public function setInstallationUrl(string $installationUrl): void
    {
        $this->installationUrl = $installationUrl;
    }

    /**
     * @param string $apiKey
     */
    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param string $listId
     *
     * @return $this
     */
    public function setListId(string $listId): self
    {
        $this->listId = $listId;

        return $this;
    }

    /**
     * Method to add a new subscriber to a list
     *
     * @param array $values
     *
     * @return array
     */
    public function subscribe(array $values): array
    {
        $result = $this->buildAndSend('subscribe', $values);

        /**
         * Prepare the array to return
         */
        $notice = [
            'status' => true,
            'message' => '',
        ];

        /**
         * Handle results
         */
        switch (strval($result)) {
            case '1':
                $notice['message'] = 'Subscribed.';

                break;
            case 'Already subscribed.':
                $notice['message'] = $result;

                break;
            default:
                $notice = [
                    'status' => false,
                    'message' => $result
                ];

                break;
        }

        return $notice;
    }

    /**
     * Updating a subscriber using the email like a reference/key
     * If the email doesn't exist in the current list, this will create a new subscriber
     *
     * @param string $email
     * @param array $values
     *
     * @return array
     */
    public function update(string $email, array $values): array
    {
        $values = array_merge([
            'email' => $email
        ], $values);

        return $this->subscribe($values);
    }

    /**
     * Method to unsubscribe a user from a list
     *
     * @param string $email
     *
     * @return array
     */
    public function unsubscribe(string $email): array
    {
        $result = $this->buildAndSend('unsubscribe', ['email' => $email]);

        /**
         * Prepare the array to return
         */
        $notice = [
            'status' => true,
            'message' => '',
        ];

        /**
         * Handle results
         */
        switch (strval($result)) {
            case '1':
                $notice['message'] = 'Unsubscribed';

                break;
            default:
                $notice = [
                    'status' => false,
                    'message' => $result
                ];

                break;
        }

        return $notice;
    }

    /**
     * Method to delete a user from a list
     *
     * @param string $email
     *
     * @return array
     */
    public function delete(string $email): array
    {
        $result = $this->buildAndSend('/api/subscribers/delete.php', ['email' => $email]);

        /**
         * Prepare the array to return
         */
        $notice = [
            'status' => true,
            'message' => '',
        ];

        /**
         * Handle results
         */
        switch (strval($result)) {
            case '1':
                $notice['message'] = 'Deleted';

                break;
            default:
                $notice = [
                    'status' => false,
                    'message' => $result
                ];

                break;
        }

        return $notice;
    }

    /**
     * Method to get the current status of a subscriber.
     * Success: Subscribed, Unsubscribed, Unconfirmed, Bounced, Soft bounced, Complained
     * Error: No data passed, Email does not exist in list, etc.
     *
     * @param string $email
     *
     * @return string
     */
    public function status(string $email): string
    {
        $url = 'api/subscribers/subscription-status.php';

        return $this->buildAndSend($url, ['email' => $email]);
    }

    /**
     * Gets the total active subscriber count
     *
     * @return string
     */
    public function count(): string
    {
        $url = 'api/subscribers/active-subscriber-count.php';

        return $this->buildAndSend($url, []);
    }

    /**
     * Create a campaign based on the input params. See API (https://sendy.co/api#4) for parameters.
     * Bug: The API doesn't save the listIds passed to Sendy.
     *
     * @param array $options
     * @param array $content
     * @param bool $send : Set this to true to send the campaign
     *
     * @return string
     * @throws \Exception
     */
    public function createCampaign(array $options, array $content, bool $send = false): string
    {
        $url = '/api/campaigns/create.php';

        if (empty($options['from_name'])) {
            throw new \InvalidArgumentException('From Name is not set');
        }

        if (empty($options['from_email'])) {
            throw new \InvalidArgumentException('From Email is not set');
        }

        if (empty($options['reply_to'])) {
            throw new \InvalidArgumentException('Reply To address is not set');
        }

        if (empty($options['subject'])) {
            throw new \InvalidArgumentException('Subject is not set');
        }

        // 'plain_text' field can be included, but optional
        if (empty($content['html_text'])) {
            throw new \InvalidArgumentException('Campaign Content (HTML) is not set');
        }

        if ($send) {
            if (empty($options['brand_id'])) {
                throw new \InvalidArgumentException('Brand ID should be set for Draft campaigns');
            }
        }

        // list IDs can be single or comma-separated values
        if (empty($options['list_ids'])) {
            $options['list_ids'] = $this->listId;
        }

        // should we send the campaign (1) or save as Draft (0)
        $options['send_campaign'] = $send ? 1 : 0;

        return $this->buildAndSend($url, array_merge($options, $content));
    }

    /**
     * @param string $url
     * @param array $values
     *
     * @return string
     */
    private function buildAndSend(string $url, array $values): string
    {
        /**
         * Merge the passed in values with the options for return
         * Passing listId too, because old API calls use list, new ones use listId
         */
        $content = array_merge($values, [
            'list' => $this->listId,
            'list_id' => $this->listId, # ¯\_(ツ)_/¯
            'api_key' => $this->apiKey,
            'boolean' => 'true',
        ]);

        try {
            $response = $this->httpClient->post($url, [
                'form_params' => $content,
                'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
            ]);

            return (string)$response->getBody();
        } catch (RequestException $e) {
            // Handle the exception as needed
            return $e->getMessage();
        }
    }

    /**
     * Checks the properties
     *
     * @throws \Exception
     */
    private function checkProperties(): void
    {
        if (!isset($this->listId)) {
            throw new \InvalidArgumentException('[listId] is not set');
        }

        if (!isset($this->installationUrl)) {
            throw new \InvalidArgumentException('[installationUrl] is not set');
        }

        if (!isset($this->apiKey)) {
            throw new \InvalidArgumentException('[apiKey] is not set');
        }