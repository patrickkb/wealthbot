<?php

namespace Wealthbot\ClientBundle\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Wealthbot\ClientBundle\Entity\ClientAccount;
use Wealthbot\ClientBundle\Entity\AccountGroup;
use Wealthbot\UserBundle\Entity\Profile;
use Wealthbot\ClientBundle\Model\ClientAccountOwner;
use Wealthbot\ClientBundle\Model\SystemAccount;
use Wealthbot\SignatureBundle\Entity\DocumentSignature;
use Wealthbot\SignatureBundle\Model\Envelope;
use Wealthbot\SignatureBundle\Repository\SignableObjectRepositoryInterface;
use Wealthbot\UserBundle\Entity\User;

/**
 * RiaCompanyInformationRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ClientAccountRepository extends EntityRepository implements SignableObjectRepositoryInterface
{
    public function getAccountsByClientIdAndAccountTypeId($clientId, $accountType)
    {
        $q = $this->getEntityManager()->createQuery("SELECT a FROM WealthbotClientBundle:ClientAccount a LEFT JOIN a.accountType t
         WHERE a.client_id=:client_id AND t.type=:account_type");

        $q->setParameters(array('client_id' => $clientId, 'account_type' => $accountType));

        return $q->getResult();
    }

    public function getInvestmentAccountsByClientId($clientId)
    {
        $investmentAccounts = $this->findByClientIdAndNotGroup($clientId, AccountGroup::GROUP_EMPLOYER_RETIREMENT);
        return $investmentAccounts;
    }

    public function getRetirementAccountsByClientId($clientId)
    {
        $retirementAccounts = $this->findByClientIdAndGroup($clientId, AccountGroup::GROUP_EMPLOYER_RETIREMENT);
        return $retirementAccounts;
    }

    public function hasRetirementAccount(User $client)
    {
        $retirementAccounts = $this->getRetirementAccountsByClientId($client->getId());

        return count($retirementAccounts) ? true : false;
    }

    public function findByClientIdAndGroup($clientId, $group)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.client_id = :client_id AND g.name = :group')
            ->setParameters(array(
                'client_id' => $clientId,
                'group' => $group
            ));

        return $qb->getQuery()->getResult();
    }

    public function findByClientIdAndNotGroup($clientId, $group)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.client_id = :client_id AND g.name != :group')
            ->setParameters(array(
                    'client_id' => $clientId,
                    'group' => $group
                ));

        return $qb->getQuery()->getResult();
    }

    protected function findNotOpenedAccountsByClientIdQuery($clientId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.client_id = :client_id')
            ->andWhere('(g.name = :group AND ca.process_step != :step1) OR (g.name != :group AND ca.process_step != :step2)')
            ->setParameters(array(
                    'client_id' => $clientId,
                    'group' => AccountGroup::GROUP_EMPLOYER_RETIREMENT,
                    'step1' => ClientAccount::PROCESS_STEP_COMPLETED_CREDENTIALS,
                    'step2' => ClientAccount::PROCESS_STEP_FINISHED_APPLICATION
                ));

        return $qb->getQuery();
    }

    protected function findOpenedAccountsByClientIdQuery($clientId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.client_id = :client_id')
            ->andWhere('(g.name = :group AND ca.process_step = :step1) OR (g.name != :group AND ca.process_step >= :step2)')
            ->setParameters(array(
                'client_id' => $clientId,
                'group' => AccountGroup::GROUP_EMPLOYER_RETIREMENT,
                'step1' => ClientAccount::PROCESS_STEP_COMPLETED_CREDENTIALS,
                'step2' => ClientAccount::PROCESS_STEP_STARTED_TRANSFER
            ));

        return $qb->getQuery();
    }

    public function findNotOpenedAccountsByClientId($clientId)
    {
        $query = $this->findNotOpenedAccountsByClientIdQuery($clientId);

        return $query->getResult();
    }

    public function findOpenedAccountsByClientId($clientId)
    {
        $query = $this->findOpenedAccountsByClientIdQuery($clientId);

        return $query->getResult();
    }

    public function findOneNotOpenedAccountByClientId($clientId)
    {
        $query = $this->findNotOpenedAccountsByClientIdQuery($clientId);
        $query->setMaxResults(1);

        return $query->getOneOrNullResult();
    }

    public function findOneByIdAndGroup($accountId, $group)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.id = :account_id')
            ->andWhere('g.name = :group')
            ->setParameters(array(
                'account_id' => $accountId,
                'group' => $group
            ))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();

    }

    public function findRetirementAccountById($accountId)
    {
        return $this->findOneByIdAndGroup($accountId, AccountGroup::GROUP_EMPLOYER_RETIREMENT);
    }

    public function hasGroup(ClientAccount $account, $group)
    {
        $result = $this->findOneByIdAndGroup($account->getId(), $group) ? true : false;
        return $result;
    }

    public function findOneByIdAndType($accountId, $type)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.type', 't')
            ->where('ca.id = :account_id')
            ->andWhere('t.name = :type')
            ->setParameters(array(
                'account_id' => $accountId,
                'type' => $type
            ))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByIdAndTypeLike($accountId, $type)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.type', 't')
            ->where('ca.id = :account_id')
            ->andWhere('t.name LIKE :type')
            ->setParameters(array(
                'account_id' => $accountId,
                'type' => '%'.$type.'%'
            ))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function findOneByIdAndTypeIn($accountId, array $types)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.type', 't')
            ->where('ca.id = :account_id')
            ->andWhere($qb->expr()->in('t.name', $types))
            ->setParameter('account_id', $accountId)
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function isJointAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Joint') ? true : false;
        return $result;
    }

    public function isRothAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Roth') ? true : false;
        return $result;
    }

    public function isIraAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Ira') ? true : false;
        return $result;
    }

    public function isIndividualAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Personal') ? true : false;
        return $result;
    }

    public function isTaxableAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Taxable') ? true : false;
        return $result;
    }

    public function isTraditionalAccount(ClientAccount $account)
    {
        $result = $this->findOneByIdAndTypeLike($account->getId(), 'Traditional') ? true : false;
        return $result;
    }

    public function hasType(ClientAccount $clientAccount, $type)
    {
        $result = $this->findOneByIdAndType($clientAccount->getId(), $type) ? true : false;
        return $result;
    }

    public function inTypes(ClientAccount $clientAccount, array $types)
    {
        $result = $this->findOneByIdAndTypeIn($clientAccount->getId(), $types) ? true : false;
        return $result;
    }

    public function getTotalScoreByClientId($clientId, $consolidatorId = null)
    {
        $qb = $this->createQueryBuilder('ca');

        $select = "SUM(ca.value) as value, SUM(ca.monthly_contributions) as monthly_contributions,
                   SUM(ca.monthly_distributions) as monthly_distributions,  SUM(ca.sas_cash) as sas_cash";

        $qb->select($select)
            ->where('ca.client_id = :client_id')
            ->groupBy('ca.client_id')
            ->setParameter('client_id', $clientId)
            ->setMaxResults(1);

        if (is_numeric($consolidatorId)) {
            $qb->andWhere('ca.unconsolidated = 0')
                ->andWhere('ca.consolidator_id = :consolidator_id OR ca.id = :consolidator_id')
                ->setParameter('consolidator_id', $consolidatorId);
        }

        $result = $qb->getQuery()->getOneOrNullResult();

        if (null === $result) {
            $result = array(
                'value' => 0,
                'monthly_contributions' => 0,
                'monthly_distributions' => 0,
                'sas_cash' => 0
            );
        }

        return $result;
    }

    public function getTotalScoreById($id)
    {
        $qb = $this->createQueryBuilder('ca');

        $select = "SUM(ca.value) as value, SUM(ca.monthly_contributions) as monthly_contributions,
                   SUM(ca.monthly_distributions) as monthly_distributions,  SUM(ca.sas_cash) as sas_cash";

        $qb->select($select)
            ->where('ca.id = :id')
            ->groupBy('ca.id')
            ->setParameter('id', $id)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (null === $result) {
            $result = array(
                'value' => 0,
                'monthly_contributions' => 0,
                'monthly_distributions' => 0,
                'sas_cash' => 0
            );
        }

        return $result;
    }

    public function findWithBeneficiariesByClientId($clientId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->leftJoin('gt.type', 't')
            ->where('ca.client_id = :client_id')
            ->andWhere('(g.name = :group_rollover OR (g.name != :group_retirement AND (t.name LIKE :type_ira OR t.name LIKE :type_roth)))')
            ->setParameters(array(
                'client_id'        => $clientId,
                'group_rollover'   => AccountGroup::GROUP_OLD_EMPLOYER_RETIREMENT,
                'group_retirement' => AccountGroup::GROUP_EMPLOYER_RETIREMENT,
                'type_ira'         => '%Ira%',
                'type_roth'        => '%Roth%'
            ));

        return $qb->getQuery()->getResult();
    }

    public function findOneRetirementAccountByIdAndClientId($accountId, $clientId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.id = :id')
            ->andWhere('ca.client_id = :client_id AND g.name = :group')
            ->setParameters(array(
                'id' => $accountId,
                'client_id' => $clientId,
                'group' => AccountGroup::GROUP_EMPLOYER_RETIREMENT
            ))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find consolidator for account by client id and system type of account
     *
     * @param integer $clientId
     * @param integer $systemType
     * @param array $owners
     * @param integer $selfId
     * @return null|ClientAccount
     */
    public function findConsolidatorByClientIdAndSystemTypeAndOwnersAndNotId($clientId, $systemType, array $owners, $selfId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->where('ca.client_id = :client_id')
            ->andWhere('ca.id != :self_id');

        $i = 0;
        foreach ($owners as $type => $id) {
            $qb->leftJoin('ca.accountOwners', 'ao' . $i);

            if ($type === ClientAccountOwner::OWNER_TYPE_SELF) {
                $qb->andWhere('ao' . $i . '.owner_client_id = :id_' . $i)
                    ->setParameter('id_' . $i, $id);
            } else {
                $qb->andWhere('ao' . $i . '.owner_contact_id = :id_' . $i)
                    ->setParameter('id_' . $i, $id);
            }

            $qb->andWhere('ao' . $i . '.owner_type = :type_' . $i)
                ->setParameter('type_' . $i, $type);

            $i++;
        }

        $qb->andWhere('ca.system_type = :system_type')
            ->andWhere('ca.consolidator_id IS NULL')
            ->andWhere('ca.unconsolidated = :unconsolidated')
            ->orderBy('ca.id', 'desc')
            ->setMaxResults(1);

        $qb->setParameter('self_id', $selfId)
            ->setParameter('client_id', $clientId)
            ->setParameter('system_type', $systemType)
            ->setParameter('unconsolidated', 0);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find consolidator for account by account object
     *
     * @param ClientAccount $account
     * @return null|ClientAccount
     */
    public function findConsolidatorForAccount(ClientAccount $account)
    {
        $clientId = $account->getClientId() ? $account->getClientId() : $account->getClient()->getId();

        return $this->findConsolidatorByClientIdAndSystemTypeAndOwnersAndNotId(
            $clientId,
            $account->getSystemType(),
            $account->getOwnersAsArray(),
            $account->getId()
        );
    }

    /**
     * Find new consolidator for account by client id, system type of account and old consolidator id
     *
     * @param integer $clientId
     * @param integer $systemType
     * @param string $owner
     * @param integer $oldConsolidatorId
     * @return null|ClientAccount
     */
    public function findNewConsolidatorByClientIdAndSystemTypeAndOwner($clientId, $systemType, $owner = ClientAccount::OWNER_SELF, $oldConsolidatorId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->where('ca.client_id = :client_id')
            ->andWhere('ca.system_type = :system_type')
            ->andWhere('ca.owner = :owner')
            ->andWhere('ca.consolidator_id IS NULL OR ca.consolidator_id = :old_consolidator_id')
            ->andWhere('ca.unconsolidated = :unconsolidated')
            ->setMaxResults(1);

        $qb->setParameters(
            array(
                'client_id' => $clientId,
                'system_type' => $systemType,
                'owner' => $owner,
                'old_consolidator_id' => $oldConsolidatorId,
                'unconsolidated' => 0
            )
        );

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Find new consolidator for accounts by old consolidator account object
     *
     * @param ClientAccount $oldConsolidator
     * @return null|ClientAccount
     */
    public function findNewConsolidatorForAccounts(ClientAccount $oldConsolidator)
    {
        $clientId = $oldConsolidator->getClientId() ?
            $oldConsolidator->getClientId() :
            $oldConsolidator->getClient()->getId();

        return $this->findNewConsolidatorByClientIdAndSystemTypeAndOwner(
            $clientId,
            $oldConsolidator->getSystemType(),
            $oldConsolidator->getOwner(),
            $oldConsolidator->getId()
        );
    }

    public function findConsolidatedAccountsByClientId($clientId)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->select('ca', 'agt', 'ag', 'at')
            ->leftJoin('ca.groupType', 'agt')
            ->leftJoin('agt.group', 'ag')
            ->leftJoin('agt.type', 'at')
            ->where('ca.client_id = :client_id')
            ->andWhere('ca.consolidator_id IS NULL or ca.unconsolidated = 1')
            ->setParameter('client_id', $clientId);

        return $qb->getQuery()->getResult();
    }

    public function findWhereIdIn($ids)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->where($qb->expr()->in('ca.id', $ids));

        return $qb->getQuery()->getResult();
    }

    public function findConsolidatorWhereIdIn($ids)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->where('ca.consolidator_id IS NULL')
            ->andWhere($qb->expr()->in('ca.id', $ids))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Returns array of accounts with consolidator_id = $consolidatorId and group name = $group
     *
     * @param integer $consolidatorId
     * @param string $group
     * @return array
     */
    public function findByConsolidatorIdAndGroup($consolidatorId, $group)
    {
        $qb = $this->createQueryBuilder('ca');

        $qb->leftJoin('ca.groupType', 'gt')
            ->leftJoin('gt.group', 'g')
            ->where('ca.id = :consolidator_id OR ca.consolidator_id = :consolidator_id')
            ->andWhere('g.name = :group');

        $qb->setParameters(array(
            'consolidator_id' => $consolidatorId,
            'group' => $group
        ));

        return $qb->getQuery()->getResult();
    }

    /**
     * Find transfer consolidated accounts
     *
     * @param integer $consolidatorId
     * @return array
     */
    public function findTransferConsolidatedAccounts($consolidatorId)
    {
        return $this->findByConsolidatorIdAndGroup($consolidatorId, AccountGroup::GROUP_FINANCIAL_INSTITUTION);
    }

    /**
     * Find rollover consolidated accounts
     *
     * @param $consolidatorId
     * @return array
     */
    public function findRolloverConsolidatedAccounts($consolidatorId)
    {
        return $this->findByConsolidatorIdAndGroup($consolidatorId, AccountGroup::GROUP_OLD_EMPLOYER_RETIREMENT);
    }

    /**
     * Contains sas cash
     *
     * @param array $accounts
     * @return bool
     */
    public function containsSasCash(array $accounts = array())
    {
        foreach ($accounts as $account) {
            if ($account->getSasCash() && $account->getSasCash() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is object has completed document signature for application
     *
     * @param int $applicationId
     * @return bool
     */
    public function isApplicationSigned($applicationId)
    {
        $sql = 'SELECT count(ca.id) FROM client_accounts ca
                LEFT JOIN document_signatures ds ON (ds.source_id = ca.id AND ds.type = :type)
                WHERE ca.id = :application_id AND (ds.status = :status_signed OR ds.status = :status_completed)';

        $stmt = $this->_em->getConnection()->prepare($sql);
        $stmt->bindValue('type', DocumentSignature::TYPE_OPEN_OR_TRANSFER_ACCOUNT);
        $stmt->bindValue('application_id', $applicationId);
        $stmt->bindValue('status_signed', Envelope::STATUS_SIGNED);
        $stmt->bindValue('status_completed', Envelope::STATUS_COMPLETED);

        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    public function getAccountsSum(User $user)
    {
        return $this->createQueryBuilder('ca')
            ->select('SUM(ca.value)')
            ->where('ca.client = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get client accounts by id array
     *
     * @param User $user
     * @param array $ids
     * @return array
     */
    public function getAccountsByIds(User $user, array $ids)
    {
        return $this
            ->createQueryBuilder('ca')
            ->where('ca.client = :user')
            ->setParameter('user', $user)
            ->andWhere('ca.id in (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @param $accountNumber
     * @return ClientAccount|null
     */
    public function getByAccountNumber($accountNumber)
    {
        return $this->createQueryBuilder('ca')
            ->leftJoin('ca.systemAccount', 'sa')
            ->where('sa.account_number = :number')
            ->setParameter('number', $accountNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function filterAccountsForCustodianFeeFile(User $ria, array $selectedAccounts, \DateTime $endDate)
    {
        $qb = $this->createQueryBuilder('clientAccount');

        $qb
            ->join('clientAccount.systemAccount', 'systemAccount')
            ->join('clientAccount.client', 'client')
            ->join('client.profile', 'profile')

            ->where('clientAccount.id IN (:selectedAccounts)')
            ->andWhere('client.created <= :date')
            ->andWhere('profile.ria = :ria')
            ->andWhere('profile.client_status = :status')
            ->andWhere('profile.paymentMethod = :paymentMethod')
            ->andWhere('systemAccount.status IN (:successStatuses)')

            ->setParameter('selectedAccounts', $selectedAccounts)
            ->setParameter('date', $endDate)
            ->setParameter('ria', $ria)
            ->setParameter('status', Profile::CLIENT_STATUS_CLIENT)
            ->setParameter('paymentMethod', Profile::PAYMENT_METHOD_DIRECT_DEBIT)
            ->setParameter('successStatuses', array(SystemAccount::STATUS_ACTIVE, SystemAccount::STATUS_REGISTERED))
        ;

        return $qb->getQuery()->execute();
    }

}
