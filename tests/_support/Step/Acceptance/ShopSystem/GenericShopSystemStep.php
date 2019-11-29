<?php


namespace Step\Acceptance\ShopSystem;


use Codeception\Scenario;
use Helper\Config\Customer\CustomerConfig;
use Step\Acceptance\GenericStep;

/**
 * Class GenericShopSystemStep
 * @package Step\Acceptance
 */
class GenericShopSystemStep extends GenericStep
{
    /**
     *
     */
    public const SETTINGS_TABLE_NAME = '';
    /**
     *
     */
    public const NAME_COLUMN_NAME = '';
    /**
     *
     */
    public const VALUE_COLUMN_NAME = '';
    /**
     *
     */
    public const TRANSACTION_TABLE_NAME = '';
    /**
     *
     */
    public const WIRECARD_OPTION_NAME = '';
    /**
     *
     */
    public const CURRENCY_OPTION_NAME = '';
    /**
     *
     */
    public const DEFAULT_COUNTRY_OPTION_NAME = '';

    /**
     * @var CustomerConfig;
     */
    private $customer;

    /**
     * @var array
     */
    private $mappedPaymentActions = [];

    /**
     * @return array
     */
    public function getMappedPaymentActions(): array
    {
        return $this->mappedPaymentActions;
    }
    /**
     * GenericStep constructor.
     * @param Scenario $scenario
     */
    public function __construct(Scenario $scenario)
    {
        parent::__construct($scenario);
        $this->setLocator($this->getDataFromDataFile(SHOP_SYSTEM_LOCATOR_FOLDER_PATH . static::STEP_NAME . DIRECTORY_SEPARATOR . static::STEP_NAME . 'Locators.json'));
    }

    /**
     * @param $name
     * @return mixed
     */
    public function existsInDatabase($name)
    {
        return $this->grabFromDatabase(static::SETTINGS_TABLE_NAME, static::NAME_COLUMN_NAME, [static::NAME_COLUMN_NAME => $name]);
    }

    /**
     * @param $type
     * @param $dataFileName
     */
    public function setConfigObject($type, $dataFileName): void
    {
        //check if full path provided in config file
        $dataFolderPath = '';
        if (pathinfo($dataFileName)['dirname'] === '.') {
            $dataFolderPath = CUSTOMER_DATA_FOLDER_PATH;
        }
        $this->customer = new CustomerConfig($this->getDataFromDataFile($dataFolderPath . $dataFileName));
    }

    /**
     * @return mixed
     */
    public function getCustomer()
    {
        return $this->customer;
    }

    /**
     * @param $name
     * @param $value
     */
    public function putValueInDatabase($name, $value): void
    {
        if (!$this->existsInDatabase($name)) {
            $this->haveInDatabase(static::SETTINGS_TABLE_NAME, [static::NAME_COLUMN_NAME => $name,
                static::VALUE_COLUMN_NAME => $value]);
        } else {
            $this->updateInDatabase(static::SETTINGS_TABLE_NAME,
                [static::VALUE_COLUMN_NAME => $value],
                [static::NAME_COLUMN_NAME => $name]
            );
        }
    }

    /**
     * @param $purchaseSum
     */
    public function fillBasket($purchaseSum): void
    {
        $this->amOnPage($this->getLocator()->page->product);

        $amount = intdiv((int)$purchaseSum, (int)$this->getLocator()->product->price) + 1;
        //add to basket goods to fulfill desired purchase amount
        $this->fillField($this->getLocator()->product->quantity, $amount);
        $this->click($this->getLocator()->product->add_to_cart);
    }

    /**
     * @return mixed
     */
    public function goToCheckout()
    {
        $this->amOnPage($this->getLocator()->page->checkout);
    }

    /**
     * @param $currency
     * @param $defaultCountry
     */
    public function configureShopSystemCurrencyAndCountry($currency, $defaultCountry): void
    {
        $this->putValueInDatabase(static::CURRENCY_OPTION_NAME, $currency);
        $this->putValueInDatabase(static::DEFAULT_COUNTRY_OPTION_NAME, $defaultCountry);
    }

    /**
     *
     */
    public function validateSuccessPage():void
    {
        $this->waitUntilPageLoaded($this->getLocator()->page->order_received);
        $this->see($this->getLocator()->order_received->order_confirmed_message);
    }


    /**
     * @param $paymentMethod
     * @param $paymentAction
     */
    public function validateTransactionInDatabase($paymentMethod, $paymentAction): void
    {
        $this->seeInDatabase(
            static::TRANSACTION_TABLE_NAME,
            ['transaction_type' => $this->getMappedPaymentActions()[$paymentMethod]['tx_table'][$paymentAction]]
        );
        //check that last transaction in the table is the one under test
        $transactionTypes = $this->getColumnFromDatabaseNoCriteria(static::TRANSACTION_TABLE_NAME, 'transaction_type');
        $this->assertEquals(end($transactionTypes), $this->getMappedPaymentActions()[$paymentMethod]['tx_table'][$paymentAction]);

    }
}