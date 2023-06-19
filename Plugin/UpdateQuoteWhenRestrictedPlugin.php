<?php
/**
 * Copyright © Gigadesign. All rights reserved.
 */
declare(strict_types=1);

namespace DUna\Payments\Plugin;

use DUna\Payments\Logger\Logger;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\ChangeQuoteControlInterface as Subject;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Plugin to update the quote if the state change is invalid
 *
 * @author Mark van der Werf <mark@gigadesign.nl>
 */
class UpdateQuoteWhenRestrictedPlugin
{
    /**
     * @var UserContextInterface $userContext
     */
    protected $userContext;

    /**
     * @var CartManagementInterface
     */
    protected $cartManagement;

    /**
     * @var CartRepositoryInterface
     */
    protected $quoteRepository;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param UserContextInterface $userContext
     * @param CartManagementInterface $cartManagement
     * @param CartRepositoryInterface $quoteRepository
     * @param Logger $logger
     */
    public function __construct(
        UserContextInterface $userContext,
        CartManagementInterface $cartManagement,
        CartRepositoryInterface $quoteRepository,
	Logger $logger
    ) {
        $this->userContext = $userContext;
        $this->cartManagement = $cartManagement;
        $this->quoteRepository = $quoteRepository;
	$this->logger = $logger;
    }

    /**
     * @param Subject $subject
     * @param bool $result
     * @param CartInterface $quote
     *
     * @return bool
     */
    public function afterIsAllowed(Subject $subject, bool $result, CartInterface $quote): bool
    {
        if (!$result)
        {
	    $contextUserType = (is_null($this->userContext->getUserType())) ? "null" : $this->userContext->getUserType();
            $contextUserId = (is_null($this->userContext->getUserId())) ? "null" : $this->userContext->getUserId();
            $quoteId = (is_null($quote->getId())) ? "null" : $quote->getId();
            $quoteCustomerId = (is_null($quote->getCustomerId())) ? "null" : $quote->getCustomerId();

            $this->logger->info('Context UserType ' . $contextUserType . ' userId ' . $contextUserId . ' Quote Id ' . $quoteId . ' Quote CustomerId ' . $quoteCustomerId);

            if (is_null($quote->getCustomerId()) && $this->userContext->getUserId())
            {
                $this->cartManagement->assignCustomer($quote->getId(), $this->userContext->getUserId(), $quote->getStoreId());
            }
            else
            {
                $quote->setCustomerId(null);
                $quote->setCustomerIsGuest(true);
                $quote->setCustomerEmail(null);
                $quote->setCustomerGroupId(0);
                $quote->setCustomerFirstname(null);
                $quote->setCustomerLastname(null);
                $quote->setCustomerDob(null);
                $quote->setCustomerGender(null);
                $quote->setCustomerTaxvat(null);
                $quote->setCheckoutMethod('guest');

                foreach ($quote->getAllAddresses() as $address)
                {
                    $address->setCustomerId(null);
                    $address->setCustomerAddressId(null);
                    $address->save();
                }

                $this->quoteRepository->save($quote);
            }

            return true;
        }

        return $result;
    }
}
