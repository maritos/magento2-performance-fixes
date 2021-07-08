<?php
/**
 * ObjectManager configuration loader
 *
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\App\ObjectManager;

use Magento\Framework\ObjectManager\ConfigLoaderInterface;
use Magento\Framework\Serialize\Serializer\Serialize;
use Magento\Framework\Serialize\SerializerInterface;

class ConfigLoader implements ConfigLoaderInterface
{
    /**
     * Config reader
     *
     * @var \Magento\Framework\ObjectManager\Config\Reader\Dom
     */
    protected $_reader;

    /**
     * Config reader factory
     *
     * @var \Magento\Framework\ObjectManager\Config\Reader\DomFactory
     */
    protected $_readerFactory;

    /**
     * Cache
     *
     * @var \Magento\Framework\Config\CacheInterface
     */
    protected $_cache;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * @param \Magento\Framework\Config\CacheInterface $cache
     * @param \Magento\Framework\ObjectManager\Config\Reader\DomFactory $readerFactory
     */
    public function __construct(
        \Magento\Framework\Config\CacheInterface $cache,
        \Magento\Framework\ObjectManager\Config\Reader\DomFactory $readerFactory
    ) {
        $this->_cache = $cache;
        $this->_readerFactory = $readerFactory;
    }

    /**
     * Get reader instance
     *
     * @return \Magento\Framework\ObjectManager\Config\Reader\Dom
     */
    protected function _getReader()
    {
        if (empty($this->_reader)) {
            $this->_reader = $this->_readerFactory->create();
        }
        return $this->_reader;
    }

    /**
     * {inheritdoc}
     */
    public function load($area, $first = true)
    {
        $cacheId = $area . '::DiConfig';
        $lockCacheKey = join('_', ['init_config_id_', $cacheId]);

        $data = $this->_cache->load($cacheId);
        $first = false;

        if ($data === false) {
            if ($this->_cache->load($lockCacheKey) === false) {
                if ($first) {
                    $uSleepTime = rand(1, 10) * 1000;
                    usleep($uSleepTime); //1000000 => 1s (0.1-0.8s)
                    return $this->load($area, false);
                }

                $this->_cache->save($this->getSerializer()->serialize([]), $lockCacheKey, [], 30);
            } else {
                $uSleepTime = rand(1, 100) * 1000;

                return $this->load($area, false);
            }

            $data = $this->_getReader()->read($area);
            $this->_cache->save($this->getSerializer()->serialize($data), $cacheId);
            $this->_cache->remove($lockCacheKey);
        } else {
            $data = $this->getSerializer()->unserialize($data);
        }

        return $data;
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
}
