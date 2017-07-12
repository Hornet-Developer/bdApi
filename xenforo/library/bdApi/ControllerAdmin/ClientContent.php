<?php

class bdApi_ControllerAdmin_ClientContent extends XenForo_ControllerAdmin_Abstract
{
    public function actionIndex()
    {
        $clientContentModel = $this->_getClientContentModel();

        $conditions = array();
        $fetchOptions = array(
            'order' => 'date',
            'direction' => 'desc',
        );

        /* @var $helper bdApi_ControllerHelper_Admin */
        $helper = $this->getHelper('bdApi_ControllerHelper_Admin');
        $viewParams = $helper->prepareConditionsAndFetchOptions($conditions, $fetchOptions);

        $clientContents = $clientContentModel->getClientContents($conditions, $fetchOptions);
        $total = $clientContentModel->countClientContents($conditions, $fetchOptions);

        $viewParams = array_merge($viewParams, array(
            'clientContents' => $clientContents,
            'total' => $total,
        ));

        return $this->responseView('bdApi_ViewAdmin_ClientContent_List', 'bdapi_client_content_list', $viewParams);
    }

    public function actionDelete()
    {
        $id = $this->_input->filterSingle('client_content_id', XenForo_Input::UINT);
        $clientContent = $this->_getClientContentModel()->getClientContentById($id, array(
            'join' => bdApi_Model_ClientContent::FETCH_CLIENT,
        ));

        if (empty($clientContent)) {
            throw $this->responseException($this->responseError(
                new XenForo_Phrase('bdapi_client_content_not_found'),
                404
            ));
        }

        if ($this->isConfirmedPost()) {
            $dw = XenForo_DataWriter::create('bdApi_DataWriter_ClientContent');
            $dw->setExistingData($id);
            $dw->delete();

            return $this->responseRedirect(
                XenForo_ControllerResponse_Redirect::SUCCESS,
                XenForo_Link::buildAdminLink('api-client-contents')
            );
        } else {
            $viewParams = array(
                'clientContent' => $clientContent,
            );

            return $this->responseView(
                'bdApi_ViewAdmin_ClientContent_Delete',
                'bdapi_client_content_delete',
                $viewParams
            );
        }
    }

    public function actionDeleteAll()
    {
        /** @var bdApi_Model_Client $clientModel */
        $clientModel = $this->getModelFromCache('bdApi_Model_Client');
        $clientId = $this->_input->filterSingle('client_id', XenForo_Input::STRING);
        $client = $clientModel->getClientById($clientId);
        if (empty($client)) {
            return $this->responseNoPermission();
        }

        if ($this->isConfirmedPost()) {
            $this->_request->setParam('cache', 'bdApi_CacheRebuilder_ClientContentDeleteAll');
            $this->_request->setParam('options', array(
                'client_id' => $client['client_id'],
            ));

            return $this->responseReroute('XenForo_ControllerAdmin_Tools', 'cache-rebuild');
        } else {
            $viewParams = array(
                'client' => $client,
            );

            return $this->responseView(
                'bdApi_ViewAdmin_ClientContent_DeleteAll',
                'bdapi_client_content_delete_all',
                $viewParams
            );
        }
    }

    /**
     * @return bdApi_Model_ClientContent
     */
    protected function _getClientContentModel()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->getModelFromCache('bdApi_Model_ClientContent');
    }
}
