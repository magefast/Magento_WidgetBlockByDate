<?php
/**
 * @author magefast@gmail.com www.magefast.com
 */

declare(strict_types=1);

namespace Dragonfly\WidgetBlockByDate\Block\Widget;

use Magento\Cms\Model\Block as CmsBlock;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

class CmsBlockDate extends Template implements BlockInterface, IdentityInterface
{
    /**
     * @var FilterProvider
     */
    protected $_filterProvider;

    /**
     * Storage for used widgets
     *
     * @var array
     */
    protected static $_widgetUsageMap = [];

    /**
     * Block factory
     *
     * @var BlockFactory
     */
    protected $_blockFactory;

    /**
     * @var CmsBlock
     */
    private $block;

    /**
     * @var TimezoneInterface
     */
    private $timezone;

    /**
     * @param Context $context
     * @param FilterProvider $filterProvider
     * @param BlockFactory $blockFactory
     * @param TimezoneInterface $timezone
     * @param array $data
     */
    public function __construct(
        Context           $context,
        FilterProvider    $filterProvider,
        BlockFactory      $blockFactory,
        TimezoneInterface $timezone,
        array             $data = []
    )
    {
        parent::__construct($context, $data);
        $this->_filterProvider = $filterProvider;
        $this->_blockFactory = $blockFactory;
        $this->timezone = $timezone;
    }

    /**
     * Get cache key informative items
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function getCacheKeyInfo()
    {
        $currentDate = $this->timezone->date()->format('Y.m.d');

        return [
            'BLOCK_TPL',
            $this->_storeManager->getStore()->getCode(),
            $this->getTemplateFile(),
            'base_url' => $this->getBaseUrl(),
            'template' => $this->getTemplate(),
            'current_date' => $currentDate
        ];
    }

    /**
     * Prepare block text and determine whether block output enabled or not.
     *
     * Prevent blocks recursion if needed.
     *
     * @return $this
     */
    protected function _beforeToHtml()
    {
        parent::_beforeToHtml();
        $blockId = $this->getData('block_id');
        $blockHash = get_class($this) . $blockId;

        if (isset(self::$_widgetUsageMap[$blockHash])) {
            return $this;
        }
        self::$_widgetUsageMap[$blockHash] = true;

        $block = $this->getBlock();

        if ($block && $block->isActive()) {
            try {
                $storeId = $this->getData('store_id') ?? $this->_storeManager->getStore()->getId();
                $this->setText(
                    $this->_filterProvider->getBlockFilter()->setStoreId($storeId)->filter($block->getContent())
                );
            } catch (NoSuchEntityException $e) {
            }
        }
        unset(self::$_widgetUsageMap[$blockHash]);
        return $this;
    }

    /**
     * Get identities of the Cms Block
     *
     * @return array
     */
    public function getIdentities()
    {
        $block = $this->getBlock();

        if ($block) {
            return $block->getIdentities();
        }

        return [];
    }

    /**
     * Get block
     *
     * @return CmsBlock|null
     */
    private function getBlock(): ?CmsBlock
    {
        if (!$this->isValidDateRangeValueToCurrentDate($this->getData('display_from'), $this->getData('display_to'))) {
            return null;
        }

        if ($this->block) {
            return $this->block;
        }

        $blockId = $this->getData('block_id');

        if ($blockId) {
            try {
                $storeId = $this->_storeManager->getStore()->getId();
                /** @var CmsBlock $block */
                $block = $this->_blockFactory->create();
                $block->setStoreId($storeId)->load($blockId);
                $this->block = $block;

                return $block;
            } catch (NoSuchEntityException $e) {
            }
        }

        return null;
    }

    /**
     * @param string $dateFrom
     * @param string $dateTo
     * @return bool
     */
    private function isValidDateRangeValueToCurrentDate($dateFrom = '', $dateTo = '')
    {
        if (empty($dateFrom) && empty($dateTo)) {
            return true;
        }

        if (!empty($dateFrom)) {
            $startDate = $this->timezone->date($dateFrom)->format('U');
        } else {
            $startDate = $this->timezone->date(strtotime("-1 day"))->format('U');
        }

        if (!empty($dateTo)) {
            $toDate = $this->timezone->date($dateTo)->format('U');
            $toDate = intval($toDate) - 1;
        } else {
            $toDate = $this->timezone->date(strtotime("+1 day"))->format('U');
        }

        $startDate = intval($startDate);
        $toDate = intval($toDate);

        $now = $this->timezone->date()->format('U');
        $now = intval($now);

        return (($startDate <= $now) && ($now <= $toDate));
    }
}
