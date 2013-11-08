<?php

namespace RestRemoteObject\Client;

use RestRemoteObject\Client\Rest\MethodDescriptor;
use RestRemoteObject\Client\Rest\ResponseHandler;
use RestRemoteObject\Client\Rest\Authentication\AuthenticationStrategyInterface;
use RestRemoteObject\Client\Rest\Versioning\VersioningStrategyInterface;
use RestRemoteObject\Client\Rest\ResponseHandler\ResponseHandlerInterface;
use RestRemoteObject\Client\Rest\ResponseHandler\DefaultResponseHandler;
use RestRemoteObject\Client\Rest\Feature\FeatureInterface;

use Zend\Http\Client as HttpClient;
use Zend\Server\Client as ClientInterface;

class Rest implements ClientInterface
{
    /**
     * @var string $format
     */
    protected $format;

    /**
     * @var HttpClient $client
     */
    protected $client;

    /**
     * @var AuthenticationStrategyInterface $authenticationStrategy
     */
    protected $authenticationStrategy;

    /**
     * @var VersioningStrategyInterface $versioningStrategy
     */
    protected $versioningStrategy;

    /**
     * @var ResponseHandlerInterface $responseHandler
     */
    protected $responseHandler;

    /**
     * @var FeatureInterface[] $features
     */
    protected $features = array();

    /**
     * @param string $uri
     * @param string $format
     */
    public function __construct($uri, $format = ResponseHandlerInterface::JSON_RESPONSE)
    {
        $this->uri = $uri;
        $this->format = $format;
    }

    /**
     * {@inheritDoc}
     */
    public function call($method, $params = array())
    {
        $descriptor = new MethodDescriptor($method, $params);

        $client = $this->getHttpClient();
        $client->setUri($this->uri . $descriptor->getApiResource());

        $httpMethod = $descriptor->getHttpMethod();
        $client->setMethod($httpMethod);

        switch($httpMethod) {
            case 'GET' : break; // params already in the URI
            case 'POST';
                $client->setParameterPost($params);
                break;
            case 'DELETE'; break;
            case 'PUT';
                $client->setParameterPost($params);
                break;
        }

        $request = $client->getRequest();

        $authenticationStrategy = $this->getAuthenticationStrategy();
        if ($authenticationStrategy) {
            $authenticationStrategy->authenticate($request);
        }

        $versioningStrategy = $this->getVersioningStrategy();
        if ($versioningStrategy) {
            $versioningStrategy->version($request);
        }

        foreach ($this->features as $feature) {
            $feature->apply($request);
        }

        $response = $client->send();

        $responseHandler = $this->getResponseHandler();
        return $responseHandler->buildResponse($this->format, $descriptor, $response);
    }

    /**
     * @return HttpClient
     */
    public function getHttpClient()
    {
        if (null === $this->client) {
            $this->setHttpClient(new HttpClient());
        }

        return $this->client;
    }

    /**
     * @param HttpClient $client
     * @return $this
     */
    public function setHttpClient(HttpClient $client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * Get the authentication strategy
     * @return AuthenticationStrategyInterface
     */
    public function getAuthenticationStrategy()
    {
        return $this->authenticationStrategy;
    }

    /**
     * Set the authentication strategy
     * @param AuthenticationStrategyInterface $authenticationStrategy
     * @return $this
     */
    public function setAuthenticationStrategy(AuthenticationStrategyInterface $authenticationStrategy)
    {
        $this->authenticationStrategy = $authenticationStrategy;

        return $this;
    }

    /**
     * Get the versioning strategy
     * @return VersioningStrategyInterface
     */
    public function getVersioningStrategy()
    {
        return $this->versioningStrategy;
    }

    /**
     * Set the versioning strategy
     * @param VersioningStrategyInterface $versioningStrategy
     * @return $this
     */
    public function setVersioningStrategy(VersioningStrategyInterface $versioningStrategy)
    {
        $this->versioningStrategy = $versioningStrategy;

        return $this;
    }

    /**
     * Get the response handler
     * @return ResponseHandlerInterface
     */
    public function getResponseHandler()
    {
        if (null === $this->responseHandler) {
            $this->setResponseHandler(new DefaultResponseHandler());
        }

        return $this->responseHandler;
    }

    /**
     * Set the response handler
     * @param ResponseHandlerInterface $responseHandler
     * @return $this
     */
    public function setResponseHandler(ResponseHandlerInterface $responseHandler)
    {
        $this->responseHandler = $responseHandler;

        return $this;
    }

    /**
     * Add feature
     * @param FeatureInterface $feature
     * @return $this
     */
    public function addFeature(FeatureInterface $feature)
    {
        $this->features[] = $feature;

        return $this;
    }
}
