<?php

use Behat\MinkExtension\Context\RawMinkContext;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../vendor/autoload.php';

class OneStepCheckoutContext extends RawMinkContext
{
    use PagarMe\Magento\Test\Helper\AdminAccessProvider;
    use PagarMe\Magento\Test\Helper\AdminDataProvider;
    use PagarMe\Magento\Test\Helper\CustomerDataProvider;
    use PagarMe\Magento\Test\Helper\PagarMeSettings;
    use PagarMe\Magento\Test\Helper\ProductDataProvider;
    use PagarMe\Magento\Test\Helper\Interaction;
    use PagarMe\Magento\Test\Helper\Configuration\Inovarti;

    protected $pagarMeCheckout;
    protected $product;

    /**
     * @BeforeScenario
     */
    public function setUp()
    {
        $this->setupInovarti();
        $this->setupCustomer();
        $this->setupProduct();
        $this->setupPagarMe();
    }

    /**
     * @Given i Am on checkout page using Inovarti One Step Checkout
     */
    public function iAmOnCheckoutPageUsingInovartiOneStepCheckout()
    {
        $this->setupCart();
        $this->loginOnOneStepCheckout();
    }

    /**
     * @Given fixed :amount discount for boleto payment is provided
     */
    public function fixedDiscountForBoletoPaymentIsProvided($amount)
    {
        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/boleto_discount',
            $amount
        );

        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/boleto_discount_mode',
            PagarMe_Core_Model_System_Config_Source_BoletoDiscountMode::FIXED_VALUE
        );

        \Mage::getConfig()->cleanCache();
    }

    /**
     * @Given percentual :amount discount for boleto payment is provided
     */
    public function percentualDiscountForBoletoPaymentIsProvided($amount)
    {
        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/boleto_discount',
            $amount
        );

        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/boleto_discount_mode',
            PagarMe_Core_Model_System_Config_Source_BoletoDiscountMode::PERCENTAGE
        );

        \Mage::getConfig()->cleanCache();
    }

    /**
     * @Then the absolute discount of :boletoDiscount must be informed on checkout
     */
    public function theAbsoluteDiscountOfMustBeInformedOnCheckout($boletoDiscount)
    {
        $discountElement = $this->getSession()->getPage()->find(
            'xpath',
            '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[2]//td//span'
        );

        \PHPUnit_Framework_TestCase::assertContains(
            $boletoDiscount,
            $discountElement->getText()
        );
    }

    /**
     * @Then the percentual discount of :boletoDiscount must be informed on checkout
     */
    public function thePercentualDiscountOfMustBeInformedOnCheckout($boletoDiscount)
    {
        $subTotal = preg_replace(
            "/[^0-9,.]/",
            "",
            $this->getSession()->getPage()->find(
                'xpath',
                '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[1]//td//span'
                )
            ->getText()
        );

        $shipping = preg_replace(
            "/[^0-9,.]/",
            "",
            $this->getSession()->getPage()->find(
                'xpath',
                '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[3]//td//span'
                )
            ->getText()
        );

        $discountElement = $this->getSession()->getPage()->find(
            'xpath',
            '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[2]//td//span'
        );


        $subTotal =  $subTotal + $shipping;

        $calculatedDiscount = round($subTotal * ($boletoDiscount/100), 2);

        \PHPUnit_Framework_TestCase::assertContains(
            (string) $calculatedDiscount,
            $discountElement->getText()
        );
    }

    /**
     * @When I confirm payment
     */
    public function iConfirmPayment()
    {
        $page = $this->getSession()->getPage();

        $page->find('css', '#p_method_pagarme_checkout')->click();
        $this->getSession()->wait(5000);

        $button = $page->find('css', '#pagarme-checkout-fill-info-button');

        $page->find('css', '#pagarme-checkout-fill-info-button')->click();
        $this->getSession()->wait(5000);

        $this->getSession()->switchToIframe(
            $page->find('css', 'iframe')->getAttribute('name')
        );

        $this->pagarMeCheckout = $this->getSession()->getPage();
        $this->getSession()->wait(1000);
        $this->pagarMeCheckout->pressButton('Boleto');

        $this->waitForElement(
            '#pagarme-modal-box-step-buyer-information',
            1000
        );

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-step-buyer-information .pagarme-modal-box-next-step'
        )->click();

        $this->waitForElement(
            '#pagarme-modal-box-step-customer-address-information',
            1000
        );

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-step-customer-address-information .pagarme-modal-box-next-step'
        )->click();

        $this->getSession()->switchToIframe();

        $page = $this->getSession()->wait(7000);


    }

    /**
     * @When place order
     */
    public function placeOrder()
    {
        $page = $this->getSession()->wait(1000);
        $this->getSession()->getPage()->pressButton(
            Mage::helper('pagarme_checkout')->__('Place Order')
        );

        $page = $this->getSession()->wait(8000);
    }

    /**
     * @Then the purchase must be created with success
     */
    public function thePurchaseMustBeCreatedWithSuccess()
    {
        $page = $this->getSession()->getPage();

        $successMessage = $page->find('css', 'h1')
            ->getText();

        \PHPUnit_Framework_TestCase::assertEquals(
            getenv('MAGENTO_URL') . 'index.php/checkout/onepage/success/',
            $this->getSession()->getCurrentUrl()
        );

        \PHPUnit_Framework_TestCase::assertEquals(
            strtolower(
                Mage::helper(
                    'pagarme_checkout'
                )->__('Your order has been received.')
            ),
            strtolower($successMessage)
        );
    }

    /**
     * @Then a link to boleto must be provided
     */
    public function aLinkToBoletoMustBeProvided()
    {
        $page = $this->getSession()->getPage();

        \PHPUnit_Framework_TestCase::assertContains(
            'Para imprimir o boleto',
            $page->find(
                'css',
                '.pagarme_info_boleto'
            )->getText()
        );

        \PHPUnit_Framework_TestCase::assertContains(
            'https://pagar.me',
            $page ->find(
                'css',
                '.pagarme_info_boleto a'
            )->getAttribute('href')
        );
    }

    /**
     * @Given :interestRate interest rate for multi installment payment
     */
    public function interestRateForMultiInstallmentPayment($interestRate)
    {
        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/interest_rate',
            $interestRate
        );

        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/free_installments',
            1
        );

        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/max_installments',
            12
        );

        \Mage::getConfig()->cleanCache();
    }

    /**
     * @When I confirm payment using :installments installments
     */
    public function iConfirmPaymentUsingInstallments($installments)
    {
        $page = $this->getSession()->getPage();

        $page->find('css', '#p_method_pagarme_checkout')->click();
        $this->getSession()->wait(5000);

        $button = $page->find('css', '#pagarme-checkout-fill-info-button');

        $page->find('css', '#pagarme-checkout-fill-info-button')->click();
        $this->getSession()->wait(5000);

        $this->getSession()->switchToIframe(
            $page->find('css', 'iframe')->getAttribute('name')
        );

        $this->pagarMeCheckout = $this->getSession()->getPage();
        $this->getSession()->wait(1000);
        $this->pagarMeCheckout->pressButton('Cartão de crédito');

        $this->waitForElement(
            '#pagarme-modal-box-step-buyer-information',
            1000
        );

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-step-buyer-information .pagarme-modal-box-next-step'
        )->click();

        $this->waitForElement(
            '#pagarme-modal-box-step-customer-address-information',
            1000
        );

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-step-customer-address-information .pagarme-modal-box-next-step'
        )->click();

        $this->getSession()->wait(1000);
        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-credit-card-number'
        )->setValue('4111111111111111');

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-credit-card-name'
        )->setValue($this->customer->getName());

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-credit-card-expiration'
        )->setValue('1019');

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-credit-card-cvv'
        )->setValue('123');


        $field = $this->pagarMeCheckout->find(
            'css',
            "[data-value='$installments']"
        );

        $field->click();

        $this->pagarMeCheckout->find(
            'css',
            '#pagarme-modal-box-step-credit-card-information .pagarme-modal-box-next-step'
        )->click();

        $this->getSession()->switchToIframe();

        $page = $this->getSession()->wait(7000);
    }

    /**
     * @Then the percentual interest of :interestRate over :installments installments must be informed on checkout
     */
    public function thePercentualInterestOfOverInstallmentsMustBeInformedOnCheckout($interestRate, $installments)
    {
        $page = $this->getSession()->wait(10000);
        $subTotal = preg_replace(
            "/[^0-9,.]/",
            "",
            $this->getSession()->getPage()->find(
                'xpath',
                '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[1]//td//span'
                )
            ->getText()
        );

        $shipping = preg_replace(
            "/[^0-9,.]/",
            "",
            $this->getSession()->getPage()->find(
                'xpath',
                '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[2]//td//span'
                )
            ->getText()
        );

        $interest = preg_replace(
            "/[^0-9,.]/",
            "",
            $this->getSession()->getPage()->find(
                'xpath',
                '//*[@class="onestepcheckout-cart-table"]//tfoot//tr[3]//td//span'
                )
            ->getText()
        );

        $subTotalWithoutInterest = $subTotal + $shipping;

        $totalInterest = ($interestRate/100) * $installments;

        $interestAmount = $subTotalWithoutInterest * $totalInterest;
        $interestAmount = round($interestAmount, 2);

        \PHPUnit_Framework_TestCase::assertEquals(
            $interestAmount,
            $interest
        );
    }


    /**
     * @AfterScenario
     */
    public function tearDown()
    {
        $this->disableInovartiOneStepCheckout();
    }

    private function setupCustomer()
    {
        $this->customer = $this->getCustomer();
        $this->customer->save();

        $this->customerAddress = $this->getCustomerAddress();
        $this->customerAddress->setCustomerId($this->customer->getId());
        $this->customerAddress->save();
    }

    private function setupProduct()
    {
        $this->product = $this->getProduct();
        $this->product->save();

        $stock = $this->getProductStock();
        $stock->assignProduct($this->product);
        $stock->save();
    }

    private function setupInovarti()
    {
        $this->enableInovartiOneStepCheckout();

        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/boleto_discount_mode',
            PagarMe_Core_Model_System_Config_Source_BoletoDiscountMode::NO_DISCOUNT
        );

        \Mage::getConfig()->cleanCache();
    }

    private function setupPagarMe()
    {
        \Mage::getModel('core/config')->saveConfig('payment/pagarme_settings/active', 1);
        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/api_key',
            PAGARME_API_KEY
        );
        \Mage::getModel('core/config')->saveConfig(
            'payment/pagarme_settings/encryption_key',
            PAGARME_ENCRYPTION_KEY
        );
    }

    private function setupCart()
    {
        $session = $this->getSession();

        $session->visit(getenv('MAGENTO_URL'));
        $page = $session->getPage();

        $page->clickLink($this->product->getName());

        $page->pressButton(
            Mage::helper('pagarme_checkout')->__('Add to Cart')
        );

        $page->pressButton(
            Mage::helper('pagarme_checkout')->__('Proceed to Checkout')
        );
    }

    private function loginOnOneStepCheckout()
    {
        $page = $this->getSession()->getPage();

        $page->fillField(
            Mage::helper('pagarme_checkout')->__('Email Address'),
            $this->customer->getEmail()
        );

        $page->fillField(
            Mage::helper('pagarme_checkout')->__('Password'),
            $this->customer->getPassword()
        );

        $page->pressButton('Login');

        $this->getSession()->wait(2000);
    }
}
