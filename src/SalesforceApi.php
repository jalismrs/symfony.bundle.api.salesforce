<?php
declare(strict_types = 1);

namespace Jalismrs\SalesforceApiBundle;

use Jalismrs\ApiThrottlerBundle\ApiThrottler;
use Maba\GentleForce\RateLimit\UsageRateLimit;
use QueryResult;
use SforceEnterpriseClient;
use SObject;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use function vsprintf;

/**
 * Class SalesforceClient
 *
 * @package App\Service\Api
 */
class SalesforceApi
{
    public const PARAMETER_USERNAME = 'salesforce.username';
    public const PARAMETER_PASSWORD = 'salesforce.password';
    public const PARAMETER_TOKEN    = 'salesforce.token';
    
    private const THROTTLER_KEY = 'salesforce_api';
    
    /**
     * apiThrottler
     *
     * @var \Jalismrs\ApiThrottlerBundle\ApiThrottler
     */
    private ApiThrottler $apiThrottler;
    /**
     * client
     *
     * @var \SforceEnterpriseClient
     */
    private SforceEnterpriseClient $client;
    
    /**
     * SalesforceApi constructor.
     *
     * @param \Jalismrs\ApiThrottlerBundle\ApiThrottler                                 $apiThrottler
     * @param \Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface $parameterBag
     * @param \SforceEnterpriseClient                                                   $sforceEnterpriseClient
     *
     * @throws \Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException
     */
    public function __construct(
        ApiThrottler $apiThrottler,
        ParameterBagInterface $parameterBag,
        SforceEnterpriseClient $sforceEnterpriseClient
    ) {
        $this->apiThrottler = $apiThrottler;
        $this->client       = $sforceEnterpriseClient;
        
        dd(
            $this->client
        );
        
        $this->client->createConnection(
            __DIR__ . '/../salesforce.wsdl.xml'
        );
        $this->client->login(
            $parameterBag->get(self::PARAMETER_USERNAME),
            vsprintf(
                '%s%s',
                [
                    $parameterBag->get(self::PARAMETER_PASSWORD),
                    $parameterBag->get(self::PARAMETER_TOKEN),
                ]
            )
        );
        
        $this->apiThrottler->registerRateLimits(
            self::THROTTLER_KEY,
            [
                new UsageRateLimit(
                    100000,
                    60 * 60 * 24
                ),
            ]
        );
    }
    
    /**
     * queryOne
     *
     * @param string $query
     *
     * @return \SObject|null
     *
     * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
     */
    public function queryOne(
        string $query
    ) : ?SObject {
        $queryResult = $this->query($query);
    
        return $queryResult->size === 0
            ? null
            : $queryResult->current();
    }
    
    /**
     * queryOneOrFails
     *
     * @param string $query
     *
     * @return \SObject
     *
     * @throws \Jalismrs\SalesforceApiBundle\ApiException
     * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
     */
    public function queryOneOrFails(
        string $query
    ) : SObject {
        $result = $this->queryOne($query);
        if ($result === null) {
            throw new ApiException(
                'No result'
            );
        }
        
        return $result;
    }
    
    /**
     * query
     *
     * @param string $query
     *
     * @return \QueryResult
     *
     * @throws \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException
     */
    public function query(
        string $query
    ) : QueryResult {
        $this->apiThrottler->waitAndIncrease(
            self::THROTTLER_KEY,
            ''
        );
        
        return $this->client->query($query);
    }
}
