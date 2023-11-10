<?php

namespace APP\plugins\generic\preprintToJournal\controllers\tab\service;

use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridCellProvider;
use APP\plugins\generic\preprintToJournal\classes\models\Service;
use APP\plugins\generic\preprintToJournal\classes\models\RemoteService;

class PreprintToJournalServiceGridCellProvider extends GridCellProvider
{
    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();

        assert(($element instanceof Service || $element instanceof RemoteService) && !empty($columnId));

        return ['label' => match($columnId) {
            'name', 'url', 'ip' => $element->{$columnId},
            'status'            => __($element->getStatusResponse()),
        }];
    }
}
