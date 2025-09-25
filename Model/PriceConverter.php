<?php

declare(strict_types=1);

namespace Superpayments\SuperPayment\Model;

class PriceConverter
{
    /**
     * @param float $price
     * @return int
     */
    public function minorUnitAmount($price): int
    {
        if (extension_loaded('bcmath')) {
            $finalAmount = bcmul((string) $price, '100', 0);
        } else {
            $finalAmount = number_format(($price*100), 0, '', '');
        }

        return (int) $finalAmount;
    }
}
