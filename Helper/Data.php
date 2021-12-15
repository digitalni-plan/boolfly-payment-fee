<?php declare(strict_types=1);

namespace Boolfly\PaymentFee\Helper;

use InvalidArgumentException;
use Magento\Directory\Model\PriceCurrency;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\Unserialize\Unserialize;
use Magento\Quote\Model\Quote;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Psr\Log\LoggerInterface;
use Magento\Catalog\Helper\Data as CatalogHelper;

class Data extends AbstractHelper
{
    const CONFIG_PAYMENT_FEE = 'paymentfee/config/';
    const TOTAL_CODE = 'fee_amount';

    public ?array $methodFee = null;

    protected SerializerInterface $serializer;
    protected PricingHelper $pricingHelper;
    protected PriceCurrency $priceCurrency;
    protected ?LoggerInterface $logger;
    private CatalogHelper $catalogHelper;

    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager,
        PricingHelper $pricingHelper,
        CatalogHelper $catalogHelper,
        PriceCurrency $priceCurrency
    ) {
        parent::__construct($context);
        if (interface_exists(SerializerInterface::class)) {
            // >= Magento 2.2
            $this->serializer = $objectManager->get(SerializerInterface::class);
        } else {
            // < Magento 2.2
            $this->serializer = $objectManager->get(Unserialize::class);
        }
        $this->_getMethodFee();
        $this->pricingHelper = $pricingHelper;
        $this->priceCurrency = $priceCurrency;
        $this->logger = $context->getLogger();
        $this->catalogHelper = $catalogHelper;
    }

    /**
     * Retrieve Payment Method Fees from Store Config
     */
    protected function _getMethodFee(): ?array
    {
        if (is_null($this->methodFee)) {
            try {
                $initialFees = $this->getConfig('fee');
                $fees        = is_array($initialFees) ? $initialFees : $this->serializer->unserialize($initialFees);
            } catch (InvalidArgumentException $e) {
                $fees = [];
            }

            if (is_array($fees)) {
                foreach ($fees as $fee) {
                    $this->methodFee[$fee['payment_method']] = [
                        'fee'         => $fee['fee'],
                        'description' => $fee['description']
                    ];
                }
            }
        }
        return $this->methodFee;
    }

    /**
     * Retrieve Store Config
     * @param string $field
     * @return mixed|null
     */
    public function getConfig(string $field = '')
    {
        if ($field) {
            $storeScope = ScopeInterface::SCOPE_STORE;
            return $this->scopeConfig->getValue(self::CONFIG_PAYMENT_FEE . $field, $storeScope);
        }
        return null;
    }

    /**
     * Check if Extension is Enabled config
     */
    public function isEnabled(): bool
    {
        return (bool) $this->getConfig('enabled');
    }

    public function canApply(Quote $quote): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if ($method = $quote->getPayment()->getMethod()) {
            if (isset($this->methodFee[$method])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Quote $quote
     * @return float
     */
    public function getFee(Quote $quote): float
    {
        $method  = $quote->getPayment()->getMethod();
        $fee     = $this->methodFee[$method]['fee'];
        $feeType = $this->getFeeType();

        if ($feeType != AbstractCarrier::HANDLING_TYPE_FIXED) {
            $subTotal = $quote->getSubtotal();
            $fee = $subTotal * ($fee / 100);
        }

        // Add tax to fee amount
        $fee += $this->getTaxAmountOnFee($fee, $quote);

        // $fee = $this->pricingHelper->currency($fee, false, false);
        return $this->priceCurrency->round($fee);
    }

    public function getTaxAmountOnFee($feeAmount, Quote $quote): float {
        $taxClass = $this->getTaxClass();
        $billingAddress  = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        if (!$feeAmount || !$taxClass) {
            return 0;
        }

        $pseudoProduct = new DataObject();
        $pseudoProduct->setTaxClassId($taxClass);

        $amountWithTax = (float) $this->catalogHelper->getTaxPrice(
            $pseudoProduct,
            $feeAmount,
            true,
            $shippingAddress,
            $billingAddress,
            null,
            null,
            false
        );

        return $amountWithTax - $feeAmount;
    }

    public function getTaxAmountFromFee($feeAmountInclTax, Quote $quote): float {
        $taxClass = $this->getTaxClass();
        $billingAddress  = $quote->getBillingAddress();
        $shippingAddress = $quote->getShippingAddress();

        if (!$feeAmountInclTax || !$taxClass) {
            return 0;
        }

        $pseudoProduct = new DataObject();
        $pseudoProduct->setTaxClassId($taxClass);

        $amountExclTax = (float) $this->catalogHelper->getTaxPrice(
            $pseudoProduct,
            $feeAmountInclTax,
            false,
            $shippingAddress,
            $billingAddress,
            null,
            null,
            true
        );

        return $feeAmountInclTax - $amountExclTax;
    }

    /**
     * Retrieve Fee type from Store config (Percent or Fixed)
     */
    public function getFeeType(): string
    {
        return (string) $this->getConfig('fee_type');
    }

    public function getTaxClass(): int
    {
        return (int) $this->getConfig('tax_class');
    }
}
