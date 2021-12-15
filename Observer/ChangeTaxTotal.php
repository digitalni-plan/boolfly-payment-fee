<?php

namespace Boolfly\PaymentFee\Observer;

use Magento\Framework\DataObject;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Boolfly\PaymentFee\Helper\Data as PaymentFeeHelper;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;

class ChangeTaxTotal implements ObserverInterface {
//    public $additionalTaxAmt = 0;

    private PaymentFeeHelper $paymentFeeHelper;
    private CatalogHelper $catalogHelper;

    public function __construct(
        PaymentFeeHelper $paymentFeeHelper,
        CatalogHelper $catalogHelper
    ) {
        $this->paymentFeeHelper = $paymentFeeHelper;
        $this->catalogHelper = $catalogHelper;
    }

    public function execute(Observer $observer)
    {
        /** @var Total */
        $total = $observer->getTotal();

        /** @var Quote $quote */
        $quote = $observer->getQuote();

//        $billingAddress  = $quote->getBillingAddress();
//        $shippingAddress = $quote->getShippingAddress();
//        $taxClass = $this->paymentFeeHelper->getTaxClass();

        $feeAmountInclTax = (float) $total->getBaseFeeAmount();
        $feeTaxAmount = $this->paymentFeeHelper->getTaxAmountFromFee($feeAmountInclTax, $quote);
        $total->addTotalAmount('tax', $feeTaxAmount);

//        return;
//        /** @var Magento\Quote\Model\Quote\Address\Total */
//        $total = $observer->getData('total');
//        $this->additionalTaxAmt = $total->getData('base_fee_amount');

        //make sure tax value exist
//        if (count($total->getAppliedTaxes()) > 0) {
//            $total->addTotalAmount('tax', $this->additionalTaxAmt);
//        }


    }
}
