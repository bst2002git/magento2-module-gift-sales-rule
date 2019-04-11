<?php
/**
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\GiftSalesRule
 * @author    Maxime Queneau <maxime.queneau@smile.fr>
 * @copyright 2019 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\GiftSalesRule\Helper;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Rule\Model\Condition\Sql\Builder;
use Magento\SalesRule\Api\Data\RuleInterface;
use Smile\GiftSalesRule\Api\Data\GiftRuleInterface;
use Smile\GiftSalesRule\Api\GiftRuleRepositoryInterface;

/**
 * Rule helper
 *
 * @author    Maxime Queneau <maxime.queneau@smile.fr>
 * @copyright 2019 Smile
 */
class Cache extends AbstractHelper
{
    /**
     * Cache
     */
    const CACHE_DATA_TAG   = "gift_rule_cache";
    const CACHE_IDENTIFIER = "gift_rule_product_";

    const DATA_MAXIMUM_NUMBER_PRODUCT = "maximum_number_product";
    const DATA_ITEMS                  = "items";

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var GiftRuleRepositoryInterface
     */
    protected $giftRuleRepository;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var Builder
     */
    protected $sqlBuilder;

    /**
     * GiftSalesRuleCache constructor.
     *
     * @param Context                     $context
     * @param CacheInterface              $cache
     * @param GiftRuleRepositoryInterface $giftRuleRepository
     * @param CollectionFactory           $productCollectionFactory
     * @param Builder                     $sqlBuilder
     */
    public function __construct(
        Context $context,
        CacheInterface $cache,
        GiftRuleRepositoryInterface $giftRuleRepository,
        CollectionFactory $productCollectionFactory,
        Builder $sqlBuilder
    ) {
        $this->cache = $cache;
        $this->giftRuleRepository = $giftRuleRepository;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->sqlBuilder = $sqlBuilder;

        parent::__construct($context);
    }

    /**
     * Save cached gift rule
     *
     * @param int|string                                                      $identifier
     * @param \Magento\AdvancedSalesRule\Model\Rule\Condition\Product\Combine $action
     * @param int|GiftRuleInterface                                           $giftRule
     *
     * @return array
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function saveCachedGiftRule($identifier, $action, $giftRule)
    {
        if (!$giftRuleData = $this->cache->load(self::CACHE_IDENTIFIER . $identifier)) {
            if (is_int($giftRule)) {
                /**
                 * Rules load by collection => extension attributes not present in rule entity
                 */
                /** @var GiftRuleInterface $giftRule */
                $giftRule = $this->giftRuleRepository->getById($giftRule);
            }

            /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
            $collection = $this->productCollectionFactory->create();

            $collection
                ->addStoreFilter();

            $action->collectValidatedAttributes($collection);
            $this->sqlBuilder->attachConditionToCollection($collection, $action);

            $items = [];
            $productCacheTags = [];
            foreach ($collection->getItems() as $item) {
                $items[$item->getId()] = $item->getData();
                $productCacheTags[] = Product::CACHE_TAG . '_' . $item->getEntityId();
            }
            $giftRuleData = [
                'maximum_number_product' => $giftRule->getMaximumNumberProduct(),
                'items' => $items
            ];

            $this->cache->save(
                serialize($giftRuleData),
                self::CACHE_IDENTIFIER . $identifier,
                array_merge([self::CACHE_DATA_TAG], $productCacheTags),
                3600
            );
        }

        if (!is_array($giftRuleData)) {
            $giftRuleData = unserialize($giftRuleData);
        }

        return $giftRuleData;
    }

    /**
     * Get cached gift rule
     *
     * @param int|string $giftRuleCode
     *
     * @return array
     */
    public function getCachedGiftRule($giftRuleCode)
    {
        return unserialize($this->cache->load(self::CACHE_IDENTIFIER . $giftRuleCode));
    }

    /**
     * Flush cached gift rule
     */
    public function flushCachedGiftRule()
    {
        $this->cache->clean(self::CACHE_DATA_TAG);
    }
}