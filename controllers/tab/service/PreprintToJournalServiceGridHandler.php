<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use PKP\security\Role;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\security\authorization\PolicySet;
use PKP\controllers\grid\admin\context\ContextGridRow;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use APP\plugins\generic\preprintToJournal\PreprintToJournalPlugin;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceGridRow;
use APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceGridCellProvider;

class PreprintToJournalServiceGridHandler extends GridHandler
{
    public static PreprintToJournalPlugin $plugin;

    public static function setPlugin(PreprintToJournalPlugin $plugin): void
    {
        static::$plugin = $plugin;
    }
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ],
            [
                'fetchGrid', 
                'fetchRow', 
            ]
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        $this->setTitle('plugins.generic.preprintToJournal.service.list.title');

        $serviceGridCellProvider = new PreprintToJournalServiceGridCellProvider;

        $this->addColumn(
            new GridColumn(
                'name',
                'plugins.generic.preprintToJournal.service.list.name',
                null,
                null,
                $serviceGridCellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'url',
                'plugins.generic.preprintToJournal.service.list.url',
                null,
                null,
                $serviceGridCellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'ip',
                'plugins.generic.preprintToJournal.service.list.ip',
                null,
                null,
                $serviceGridCellProvider
            )
        );

        $this->addColumn(
            new GridColumn(
                'status',
                'plugins.generic.preprintToJournal.service.list.status',
                null,
                null,
                $serviceGridCellProvider
            )
        );
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return ContextGridRow
     */
    protected function getRowInstance()
    {
        return new PreprintToJournalServiceGridRow;
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    protected function loadData($request, $filter = null)
    {
        if (static::$plugin::isOJS()) {
            return RemoteService::all()
                ->mapWithKeys(fn (RemoteService $service) => [$service->id => $service])
                ->all();
        }

        return Service::all()
            ->mapWithKeys(fn (Service $service) => [$service->id => $service])
            ->all();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\plugins\generic\preprintToJournal\controllers\tab\service\PreprintToJournalServiceGridHandler', '\PreprintToJournalServiceGridHandler');
}
