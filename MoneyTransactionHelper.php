<?php
/**
 * Created by PhpStorm.
 * User: developer
 * Date: 01/08/16
 * Time: 08:39
 */

namespace AppBundle\NewUtils;

use AppBundle\Entity\Accounts;
use AppBundle\Entity\Currency;
use AppBundle\Entity\Transaction;
use AppBundle\Entity\User;
use AppBundle\Money\HoneyMoney;
use AppBundle\Money\Money;
use AppBundle\Money\MoneyFactory;

use AppBundle\Money\TorMoney;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Container;


/**
 * Class MoneyTransactionHelper
 * @package AppBundle\NewUtils
 */
class MoneyTransactionHelper {

    /**
     * This constants we use in Fabric method to find Money object Class
     */
    const TORAccount        = 'tor_account';
    const HONEYAccount      = 'honey_account';
    const ECOAccount        = 'eco_account';
    const STRUCTUREAccount  = 'structure_account';
    const BONUSAccount      = 'bonus_account';
    const APIARYAccount     = 'apiary_account';

    /**
     * @var array
     */
    private $user_transactions_storage = array();

    /**
     * @var array
     */
    private $users_storage = array();

    /**
     * @var ObjectManager
     */
    private $object_manager;

    /**
     * @var Container
     */
    private $serviceContainer;


    /**
     * @param ObjectManager $objectManager
     * @param Container $serviceContainer
     */
    public function __construct(ObjectManager $objectManager, Container $serviceContainer)
    {
        $this->object_manager = $objectManager;
        $this->serviceContainer = $serviceContainer;
    }

    /**
     * @param User $sender
     * @param User $recipient
     * @param $senderAccountName
     * @param $recipientAccountName
     * @param $transactionSum
     * @param $receiptSum
     * @return Transaction
     * @throws \Exception
     */
    public function runMoneyTransaction(User $sender, User $recipient, $senderAccountName, $recipientAccountName, Money $transactionSum, Money $receiptSum)
    {
        if($sender->getAccounts()->getExactAccount($senderAccountName)->subtract($transactionSum)->getAmountAsInnerValue() < 0)
        {
            if($sender->getUsername() !== $this->serviceContainer->get('app.intor_user')->getIntorUser()->getUsername())
            {
                return false;
            }
        }
        $sender->getAccounts()->subtractFromAccount($senderAccountName, $transactionSum);
        $recipient->getAccounts()->addToAccount($recipientAccountName, $receiptSum);

        $member_transaction = MoneyTransactionHelper::createUserTransaction(
            $sender,
            $recipient,
            $senderAccountName,
            $recipientAccountName,
            $transactionSum,
            $receiptSum
        );

        $this->users_storage[] = $sender;
        $this->users_storage[] = $recipient;

        return true;
    }

    /**
     * @param User $user
     * @param TorMoney $transferSum
     */
    public function transferFromTorToHoney(User $user, TorMoney $transferSum)
    {
        $this->runMoneyTransaction(
            $user,
            $user,
            Accounts::TORAccount,
            Accounts::HONEYAccount,
            $transferSum,
            $this->makeExchange($transferSum, Currency::SOTCURRENCYCODE)
        );
    }

    /**
     * @return void
     */
    public function persistTransactions()
    {
        foreach($this->getUsersStorage() as $user)
        {
            $this->object_manager->persist($user);
        }
        foreach($this->getUserTransactionsStorage() as $transaction)
        {
            $this->object_manager->persist($transaction);
        }
    }

    /**
     * This method delegate the flush function to object manager
     */
    public function saveTransactions()
    {
        $this->object_manager->flush();
    }
//    /**
//     * @param User $sender
//     * @param User $recipient
//     * @param $senderAccountName
//     * @param $recipientAccountName
//     * @param Money $transactionMoney
//     * @param Money $receipt_sum
//     * @return Transaction
//     * @throws \Exception
//     */
//    public function runMoneyTransactionFromIntor(User $sender, User $recipient, $senderAccountName, $recipientAccountName, Money $transactionMoney, Money $receipt_sum)
//    {
//        $sender->getAccounts()->subtractFromAccount($senderAccountName, $transactionMoney);
//        $recipient->getAccounts()->addToAccount($recipientAccountName, $transactionMoney);
//        $member_transaction = MoneyTransactionHelper::createUserTransaction(
//            $sender,
//            $recipient,
//            $senderAccountName,
//            $recipientAccountName,
//            $transactionMoney->getAmountAsInnerValue(),
//            $receipt_sum->getAmountAsInnerValue()
//        );
//        return $member_transaction;
//    }

    /**
     * @return array
     */
    public function getUsersStorage()
    {
        return $this->users_storage;
    }

    /**
     * @return array
     */
    public function getUserTransactionsStorage()
    {
        return $this->user_transactions_storage;
    }

    /**
     * This method return the moneyObject, i Think it is useful for us
     * Work only with php7
     * @param Money $money
     * @param int $currency_code
     * @return Money
     */
    public function makeExchange(Money $money, int $currency_code)
    {
        $currency_code_of_first = $money->getCurrency();
        $ex_rate_1 = $this->object_manager->getRepository('AppBundle:Currency')->findOneBy(['currency_code' => $currency_code_of_first])->getExSaleRateToBase();
        $ex_rate_2 = $this->object_manager->getRepository('AppBundle:Currency')->findOneBy(['currency_code' => $currency_code])->getExSaleRateToBase();

        $ex_sum = $money->getAmountAsPublicValue();

        $new_money_sum = $ex_sum * $ex_rate_1/$ex_rate_2;

        return MoneyFactory::getMoney($new_money_sum, $currency_code);
    }


    /**
     * @param User $user
     */
    private function attachUserToStorage(User $user)
    {
        if(in_array($user->getUsername(), $this->users_storage))
        {
            return;
        }
        else{
            $this->users_storage[ $user->getUsername() ] = $user;
        }
    }

    /**
     * @param User $sender
     * @param User $recipient
     * @param $senderAccountName
     * @param $recipientAccountName
     * @param $transactionSum
     * @return Transaction
     * @throws \Exception
     */
    private function createUserTransaction(User $sender, User $recipient, $senderAccountName, $recipientAccountName, $transactionSum, $receipt_sum)
    {
        $transaction = new Transaction($sender, $recipient, $senderAccountName, $recipientAccountName, $transactionSum, $receipt_sum);

        if($transaction)
        {
            $this->user_transactions_storage[] = $transaction;

            return $transaction;
        }
        else
        {
            throw new \Exception('some problems with transaction');
        }
    }






//    public static function runWithdrawTransaction(User $sender, User $recipient, $senderAccountName, $recipientAccountName, $transactionSum)
//    {
//        $sender->getAccounts()->updateAccount($senderAccountName, -$transactionSum);
//        $recipient->getAccounts()->updateAccount($recipientAccountName, $transactionSum);
//        $member_transaction = UserTransactions::setNewTransaction(
//            $sender,
//            $recipient,
//            $senderAccountName,
//            $recipientAccountName,
//            $transactionSum
//        );
//        return $member_transaction;
//    }

//    /**
//     * @param User $sender
//     * @param User $recipient
//     * @param $senderAccountName
//     * @param $recipientAccountName
//     * @param Money $transactionMoney
//     * @param Money $receipt_sum
//     * @return Transaction|bool
//     * @throws \Exception
//     */
//    public function MoneyTransaction(User $sender, User $recipient, $senderAccountName, $recipientAccountName, Money $transactionMoney, Money $receipt_sum)
//    {
//        if($sender->getAccounts()->getExactAccount($senderAccountName)->subtract($transactionMoney)->getAmountAsInnerValue() < 0)
//        {
//            return false;
//        }
//        $sender->getAccounts()->subtractFromAccount($senderAccountName, $transactionMoney);
//        $recipient->getAccounts()->addToAccount($recipientAccountName, $transactionMoney);
//        $member_transaction = MoneyTransactionHelper::createUserTransaction(
//            $sender,
//            $recipient,
//            $senderAccountName,
//            $recipientAccountName,
//            $transactionMoney->getAmountAsInnerValue(),
//            $receipt_sum->getAmountAsInnerValue()
//        );
//        return $member_transaction;
//    }
}