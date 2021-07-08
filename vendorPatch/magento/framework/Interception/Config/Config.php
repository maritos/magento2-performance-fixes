<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\Interception\Config;

use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\Serialize\SerializerInterface;

/**
 * Interception config.
 *
 * Responsible for providing list of plugins configured for instance
 */
class Config implements \Magento\Framework\Interception\ConfigInterface
{
    /**
     * Type configuration
     *
     * @var \Magento\Framework\Interception\ObjectManager\ConfigInterface
     */
    protected $_omConfig;

    /**
     * Class relations info
     *
     * @var \Magento\Framework\ObjectManager\RelationsInterface
     */
    protected $_relations;

    /**
     * List of interceptable classes
     *
     * @var \Magento\Framework\ObjectManager\DefinitionInterface
     */
    protected $_classDefinitions;

    /**
     * Cache
     * @deprecated 102.0.1
     * @var \Magento\Framework\Cache\FrontendInterface
     */
    protected $_cache;

    /**
     * Cache identifier
     *
     * @var string
     */
    protected $_cacheId;

    /**
     * Configuration reader
     *
     * @var \Magento\Framework\Config\ReaderInterface
     */
    protected $_reader;

    /**
     * Inherited list of intercepted types
     *
     * @var array
     */
    protected $_intercepted = [];

    /**
     * List of class types that can not be pluginized
     *
     * @var array
     */
    protected $_serviceClassTypes = ['Interceptor'];

    /**
     * @var \Magento\Framework\Config\ScopeListInterface
     */
    protected $_scopeList;

    /**
     * @var CacheManager
     */
    private $cacheManager;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * Config constructor
     *
     * @param \Magento\Framework\Config\ReaderInterface $reader
     * @param \Magento\Framework\Config\ScopeListInterface $scopeList
     * @param \Magento\Framework\Cache\FrontendInterface $cache @deprecated
     * @param \Magento\Framework\ObjectManager\RelationsInterface $relations
     * @param \Magento\Framework\Interception\ObjectManager\ConfigInterface $omConfig
     * @param \Magento\Framework\ObjectManager\DefinitionInterface $classDefinitions
     * @param string $cacheId
     * @param SerializerInterface|null $serializer @deprecated
     * @param CacheManager $cacheManager
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __construct(
        \Magento\Framework\Config\ReaderInterface $reader,
        \Magento\Framework\Config\ScopeListInterface $scopeList,
        \Magento\Framework\Cache\FrontendInterface $cache,
        \Magento\Framework\ObjectManager\RelationsInterface $relations,
        \Magento\Framework\Interception\ObjectManager\ConfigInterface $omConfig,
        \Magento\Framework\ObjectManager\DefinitionInterface $classDefinitions,
        $cacheId = 'interception',
        SerializerInterface $serializer = null,
        CacheManager $cacheManager = null
    ) {
        $this->_omConfig = $omConfig;
        $this->_relations = $relations;
        $this->_classDefinitions = $classDefinitions;
        $this->_cache = $cache;
        $this->_cacheId = $cacheId;
        $this->_reader = $reader;
        $this->_scopeList = $scopeList;
        $this->cacheManager =
            $cacheManager ?? \Magento\Framework\App\ObjectManager::getInstance()->get(CacheManager::class);
        $this->initializeIntercepted($cacheId);
    }

    private function initializeIntercepted($cacheId, $first = true)
    {
        $lockCacheKey = join('_', ['init_config_id_', $cacheId]);
        $data = $this->cacheManager->load($cacheId);

        if ($data === null) {
            if ($this->_cache->load($lockCacheKey) === false) {
                if ($first) {
                    $uSleepTime = rand(1, 10) * 1000;
                    usleep($uSleepTime); //1000000 => 1s (0.1-0.8s)
                    $this->initializeIntercepted($cacheId, false);
                    return;
                }

                $this->_cache->save($this->getSerializer()->serialize([]), $lockCacheKey, [], 30);
            } else {
                $uSleepTime = rand(1, 100) * 1000;
                usleep($uSleepTime);
                $this->initializeIntercepted($cacheId, false);

                return;
            }

            $this->generateIntercepted($this->_classDefinitions);
            $this->cacheManager->save($this->_cacheId, $this->_intercepted);
            $this->_cache->remove($lockCacheKey);
        } else {
            $this->_intercepted = $data;
        }
    }

    /**
     * Get serializer
     *
     * @return SerializerInterface
     * @deprecated 101.0.0
     */
    private function getSerializer()
    {
        if (null === $this->serializer) {
            $this->serializer = \Magento\Framework\App\ObjectManager::getInstance()->get(Serialize::class);
        }
        return $this->serializer;
    }

    /**
     * Initialize interception config
     *
     * @param array $classDefinitions
     * @return void
     */
    public function initialize($classDefinitions = [])
    {
        $this->generateIntercepted($classDefinitions);

        $this->cacheManager->saveCompiled($this->_cacheId, $this->_intercepted);
    }

    /**
     * Process interception inheritance
     *
     * @param string $type
     * @return bool
     */
    protected function _inheritInterception($type)
    {
        $type = ltrim($type, '\\');
        if (!isset($this->_intercepted[$type])) {
            $realType = $this->_omConfig->getOriginalInstanceType($type);
            if ($type !== $realType) {
                if ($this->_inheritInterception($realType)) {
                    $this->_intercepted[$type] = true;
                    return true;
                }
            } else {
                $parts = explode('\\', $type);
                if (!in_array(end($parts), $this->_serviceClassTypes) && $this->_relations->has($type)) {
                    $relations = $this->_relations->getParents($type);
                    foreach ($relations as $relation) {
                        if ($relation && $this->_inheritInterception($relation)) {
                            $this->_intercepted[$type] = true;
                            return true;
                        }
                    }
                }
            }
            $this->_intercepted[$type] = false;
        }
        return $this->_intercepted[$type];
    }

    /**
     * @inheritdoc
     */
    public function hasPlugins($type)
    {
        if (isset($this->_intercepted[$type])) {
            return $this->_intercepted[$type];
        }
        return $this->_inheritInterception($type);
    }

    /**
     * Write interception config to cache
     *
     * @param array $classDefinitions
     */
    private function initializeUncompiled($classDefinitions = [])
    {
        $this->generateIntercepted($classDefinitions);

        $this->cacheManager->save($this->_cacheId, $this->_intercepted);
    }

    /**
     * Generate intercepted array to store in compiled metadata or frontend cache
     *
     * @param array $classDefinitions
     */
    private function generateIntercepted($classDefinitions)
    {
        $config = [];
        foreach ($this->_scopeList->getAllScopes() as $scope) {
            $config = array_replace_recursive($config, $this->_reader->read($scope));
        }
        unset($config['preferences']);
        foreach ($config as $typeName => $typeConfig) {
            if (!empty($typeConfig['plugins'])) {
                $this->_intercepted[ltrim($typeName, '\\')] = true;
            }
        }
        foreach ($config as $typeName => $typeConfig) {
            $this->hasPlugins($typeName);
        }
        foreach ($classDefinitions as $class) {
            $this->hasPlugins($class);
        }
    }
}
