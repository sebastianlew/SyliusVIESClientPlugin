<?php

declare(strict_types=1);

namespace Prometee\SyliusVIESClientPlugin\Applicator;

use InvalidArgumentException;
use Prometee\SyliusVIESClientPlugin\Entity\EuropeanChannelAwareInterface;
use Prometee\SyliusVIESClientPlugin\Entity\VATNumberAwareInterface;
use Prometee\VIESClient\Util\VatNumberUtil;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\AdjustmentInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Taxation\Applicator\OrderTaxesApplicatorInterface;
use Webmozart\Assert\Assert;

final class OrderEuropeanVATNumberApplicator implements OrderTaxesApplicatorInterface
{
    /**
     * {@inheritdoc}
     *
     * @throws InvalidArgumentException
     */
    public function apply(OrderInterface $order, ZoneInterface $zone): void
    {
        /** @var EuropeanChannelAwareInterface $channel */
        $channel = $order->getChannel();

        if (null === $channel) {
            return;
        }

        /** @var VATNumberAwareInterface $billingAddress */
        $billingAddress = $order->getBillingAddress();

        if (null === $billingAddress) {
            return;
        }

        if (null === $order->getBillingAddress()) {
            return;
        }

        if (null === $channel->getBaseCountry()) {
            return;
        }

        if (null === $channel->getEuropeanZone()) {
            return;
        }

        // These weird assignment is required for PHPStan
        $billingCountryCode = $order->getBillingAddress()->getCountryCode();

        if (false === $this->isValidForZeroEuropeanVAT($billingAddress, $billingCountryCode, $zone, $channel)) {
            return;
        }

        foreach ($order->getItems() as $item) {
            $quantity = $item->getQuantity();
            Assert::notSame($quantity, 0, 'Cannot apply tax to order item with 0 quantity.');

            $item->removeAdjustmentsRecursively(AdjustmentInterface::TAX_ADJUSTMENT);
        }
    }

    public function isValidForZeroEuropeanVAT(
        VATNumberAwareInterface $billingAddress,
        ?string $billingCountryCode,
        ZoneInterface $zone,
        EuropeanChannelAwareInterface $channel
    ): bool {
        if (null === $billingCountryCode) {
            return false;
        }

        $vatNumber = $billingAddress->getVatNumber();
        if (null === $vatNumber) {
            return false;
        }

        $vatNumberArr = VatNumberUtil::split($vatNumber);
        if (null === $vatNumberArr) {
            return false;
        }

        if (null === $channel->getBaseCountry()) {
            return false;
        }
        if ($billingCountryCode === $channel->getBaseCountry()->getCode()) {
            return false;
        }

        if ($zone !== $channel->getEuropeanZone()) {
            return false;
        }

        if ($billingCountryCode !== $vatNumberArr[0]) {
            return false;
        }

        return true;
    }
}
