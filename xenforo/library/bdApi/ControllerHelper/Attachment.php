<?php

class bdApi_ControllerHelper_Attachment extends XenForo_ControllerHelper_Abstract
{
	public function doUpload($formField, $hash, $contentType, array $contentData)
	{
		$this->_assertCanUploadAndManageAttachments($hash, $contentType, $contentData);

		$attachmentModel = $this->_getAttachmentModel();
		$attachmentHandler = $attachmentModel->getAttachmentHandler($contentType); // known to be valid
		$contentId = $attachmentHandler->getContentIdFromContentData($contentData);

		$existingAttachments = ($contentId
				? $attachmentModel->getAttachmentsByContentId($contentType, $contentId)
				: array()
		);
		$newAttachments = $attachmentModel->getAttachmentsByTempHash($hash);

		$attachmentConstraints = $attachmentHandler->getAttachmentConstraints();

		if ($attachmentConstraints['count'] > 0)
		{
			$remainingUploads = $attachmentConstraints['count'] - (count($existingAttachments) + count($newAttachments));
			if ($remainingUploads <= 0)
			{
				return $this->_controller->responseError(new XenForo_Phrase(
						'you_may_not_upload_more_files_with_message_allowed_x',
						array('total' => $attachmentConstraints['count'])
				), 403);
			}
		}

		$file = XenForo_Upload::getUploadedFile($formField);
		if (!$file)
		{
			return $this->_controller->responseError(new XenForo_Phrase('bdapi_requires_upload_x', array('field' => $formField)), 400);
		}

		$file->setConstraints($attachmentConstraints);
		if (!$file->isValid())
		{
			return $this->_controller->responseError($file->getErrors(), 403);
		}
		$dataId = $attachmentModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId());
		$attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $hash);

		return $attachmentModel->getAttachmentById($attachmentId);
	}

	public function doDelete($hash, $attachmentId)
	{
		$attachment = $this->_getAttachmentOrError($attachmentId);
		if (!$this->_getAttachmentModel()->canDeleteAttachment($attachment, $hash))
		{
			return $this->_controller->responseNoPermission();
		}

		$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
		$dw->setExistingData($attachment, true);
		$dw->delete();

		return $this->_controller->responseMessage(new XenForo_Phrase('changes_saved'));
	}

	protected function _assertCanUploadAndManageAttachments($hash, $contentType, array $contentData)
	{
		if (!$hash)
		{
			throw $this->_controller->getNoPermissionResponseException();
		}

		$attachmentHandler = $this->_getAttachmentModel()->getAttachmentHandler($contentType);
		if (!$attachmentHandler || !$attachmentHandler->canUploadAndManageAttachments($contentData))
		{
			throw $this->_controller->getNoPermissionResponseException();
		}
	}

	protected function _getAttachmentOrError($attachmentId)
	{
		$attachment = $this->_getAttachmentModel()->getAttachmentById($attachmentId);
		if (!$attachment)
		{
			throw $this->_controller->responseException($this->_controller->responseError(new XenForo_Phrase('requested_attachment_not_found'), 404));
		}

		return $attachment;
	}

	/**
	 * @return XenForo_Model_Attachment
	 */
	protected function _getAttachmentModel()
	{
		return $this->_controller->getModelFromCache('XenForo_Model_Attachment');
	}
}