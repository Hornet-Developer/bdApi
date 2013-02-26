<?php
class bdApiConsumer_XenForo_ControllerPublic_Register extends XFCP_bdApiConsumer_XenForo_ControllerPublic_Register
{
	const SESSION_KEY_REDIRECT = 'bdApiConsumer_redirect';

	public function actionExternal()
	{
		$producerCode = $this->_input->filterSingle('producer', XenForo_Input::STRING);
		$assocUserId = $this->_input->filterSingle('assoc', XenForo_Input::UINT);
		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$producer = bdApiConsumer_Option::getProducerByCode($producerCode);
		if (empty($producer))
		{
			return $this->responseNoPermission();
		}

		$externalRedirectUri = XenForo_Link::buildPublicLink('canonical:register/external', false, array(
			'producer' => $producerCode,
			'assoc' => ($assocUserId ? $assocUserId : false),
		));

		if ($this->_input->filterSingle('reg', XenForo_Input::UINT))
		{
			$redirect = XenForo_Link::convertUriToAbsoluteUri($this->getDynamicRedirect());
			XenForo_Application::get('session')->set(self::SESSION_KEY_REDIRECT, $redirect);

			return $this->responseRedirect(
				XenForo_ControllerResponse_Redirect::RESOURCE_CANONICAL,
				bdApiConsumer_Helper_Api::getRequestUrl($producer, $externalRedirectUri)
			);
		}

		// try to use the non-standard query parameter `t` first,
		// continue exchange code for access token later if that fails
		$externalToken = $this->_input->filterSingle('t', XenForo_Input::STRING);
		if (empty($externalToken))
		{
			$externalCode = $this->_input->filterSingle('code', XenForo_Input::STRING);
			if (empty($externalCode))
			{
				return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array(
					'producer' => $producer['name'],
				)));
			}

			$externalToken = bdApiConsumer_Helper_Api::getAccessTokenFromCode($producer, $externalCode, $externalRedirectUri);
			if (empty($externalToken))
			{
				return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array(
					'producer' => $producer['name'],
				)));
			}
		}

		$externalVisitor = bdApiConsumer_Helper_Api::getVisitor($producer, $externalToken);
		if (empty($externalVisitor))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array(
				'producer' => $producer['name'],
			)));
		}
		if (empty($externalVisitor['email']))
		{var_dump($externalVisitor);exit;
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error', array(
				'producer' => $producer['name'],
			)));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$existingAssoc = $userExternalModel->getExternalAuthAssociation($userExternalModel->bdApiConsumer_getProviderCode($producer), $externalVisitor['user_id']);
		if ($existingAssoc && $userModel->getUserById($existingAssoc['user_id']))
		{
			$redirect = XenForo_Application::get('session')->get(self::SESSION_KEY_REDIRECT);

			XenForo_Application::get('session')->changeUserId($existingAssoc['user_id']);
			XenForo_Visitor::setup($existingAssoc['user_id']);

			XenForo_Application::get('session')->remove(self::SESSION_KEY_REDIRECT);
			if (empty($redirect))
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirect
			);
		}

		$existingUser = false;
		$emailMatch = false;
		if (XenForo_Visitor::getUserId())
		{
			$existingUser = XenForo_Visitor::getInstance();
		}
		else if ($assocUserId)
		{
			$existingUser = $userModel->getUserById($assocUserId);
		}

		if (!$existingUser)
		{
			$existingUser = $userModel->getUserByEmail($externalVisitor['email']);
			$emailMatch = true;
		}

		if ($existingUser)
		{
			// must associate: matching user
			return $this->responseView('bdApiConsumer_ViewPublic_Register_External', 'bdapi_consumer_register', array(
				'associateOnly' => true,

				'producer' => $producer,
				'externalToken' => $externalToken,
				'externalVisitor' => $externalVisitor,

				'existingUser' => $existingUser,
				'emailMatch' => $emailMatch,
				'redirect' => $redirect
			));
		}

		$this->_assertRegistrationActive();

		// give a unique username suggestion
		$i = 2;
		$origName = $externalVisitor['username'];
		while ($userModel->getUserByName($externalVisitor['username']))
		{
			$externalVisitor['username'] = $origName . ' ' . $i++;
		}

		return $this->responseView('bdApiConsumer_ViewPublic_Register_External', 'bdapi_consumer_register', array(
			'producer' => $producer,
			'externalToken' => $externalToken,
			'externalVisitor' => $externalVisitor,
			'redirect' => $redirect,

			'customFields' => $this->_getFieldModel()->prepareUserFields($this->_getFieldModel()->getUserFields(array('registration' => true)), true),

			'timeZones' => XenForo_Helper_TimeZone::getTimeZones(),
			'tosUrl' => XenForo_Dependencies_Public::getTosUrl()
		), $this->_getRegistrationContainerParams());
	}

	public function actionExternalRegister()
	{
		$this->_assertPostOnly();

		$producerCode = $this->_input->filterSingle('producer', XenForo_Input::STRING);
		$externalToken = $this->_input->filterSingle('externalToken', XenForo_Input::STRING);

		$producer = bdApiConsumer_Option::getProducerByCode($producerCode);
		if (empty($producer))
		{
			return $this->responseNoPermission();
		}

		$externalVisitor = bdApiConsumer_Helper_Api::getVisitor($producer, $externalToken);
		if (empty($externalVisitor))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_error_occurred_while_connecting_with_x', array(
				'producer' => $producer['name'],
			)));
		}
		if (empty($externalVisitor['email']))
		{
			return $this->responseError(new XenForo_Phrase('bdapi_consumer_x_returned_unknown_error', array(
				'producer' => $producer['name'],
			)));
		}

		$userModel = $this->_getUserModel();
		$userExternalModel = $this->_getUserExternalModel();

		$doAssoc = ($this->_input->filterSingle('associate', XenForo_Input::STRING)
		|| $this->_input->filterSingle('force_assoc', XenForo_Input::UINT)
		);

		if ($doAssoc)
		{
			$associate = $this->_input->filter(array(
				'associate_login' => XenForo_Input::STRING,
				'associate_password' => XenForo_Input::STRING
			));

			$loginModel = $this->_getLoginModel();

			if ($loginModel->requireLoginCaptcha($associate['associate_login']))
			{
				return $this->responseError(new XenForo_Phrase('your_account_has_temporarily_been_locked_due_to_failed_login_attempts'));
			}

			$userId = $userModel->validateAuthentication($associate['associate_login'], $associate['associate_password'], $error);
			if (!$userId)
			{
				$loginModel->logLoginAttempt($associate['associate_login']);
				return $this->responseError($error);
			}

			$userExternalModel->updateExternalAuthAssociation(
				$userExternalModel->bdApiConsumer_getProviderCode($producer),
				$externalVisitor['user_id'],
				$userId,
				$userExternalModel->bdApiConsumer_getUserProfileField()
			);

			$redirect = XenForo_Application::get('session')->get(self::SESSION_KEY_REDIRECT);
			XenForo_Application::get('session')->changeUserId($userId);
			XenForo_Visitor::setup($userId);

			XenForo_Application::get('session')->remove(self::SESSION_KEY_REDIRECT);
			if (!$redirect)
			{
				$redirect = $this->getDynamicRedirect(false, false);
			}

			return $this->responseRedirect(
			XenForo_ControllerResponse_Redirect::SUCCESS,
			$redirect
			);
		}

		$this->_assertRegistrationActive();

		$data = $this->_input->filter(array(
			'username'   => XenForo_Input::STRING,
			'timezone'   => XenForo_Input::STRING,
		));

		if (XenForo_Dependencies_Public::getTosUrl() && !$this->_input->filterSingle('agree', XenForo_Input::UINT))
		{
			return $this->responseError(new XenForo_Phrase('you_must_agree_to_terms_of_service'));
		}

		$options = XenForo_Application::get('options');

		$writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
		if ($options->registrationDefaults)
		{
			$writer->bulkSet($options->registrationDefaults, array('ignoreInvalidFields' => true));
		}
		$writer->bulkSet($data);
		$writer->bulkSet(array(
			'gender' => $externalVisitor['gender'],
			'email' => $externalVisitor['email'],
		));

		if (!empty($externalVisitor['dob_day']) AND !empty($externalVisitor['dob_month']) AND !empty($externalVisitor['dob_year']))
		{
			$writer->set('dob_day', $externalVisitor['dob_day']);
			$writer->set('dob_month', $externalVisitor['dob_month']);
			$writer->set('dob_year', $externalVisitor['dob_year']);
		}

		if (!empty($externalVisitor['homepage']))
		{
			if (Zend_Uri::check($externalVisitor['homepage']))
			{
				$writer->set('homepage', $externalVisitor['homepage']);
			}
		}

		$auth = XenForo_Authentication_Abstract::create('XenForo_Authentication_NoPassword');
		$writer->set('scheme_class', $auth->getClassName());
		$writer->set('data', $auth->generate(''), 'xf_user_authenticate');

		$writer->set('user_group_id', XenForo_Model_User::$defaultRegisteredGroupId);
		$writer->set('language_id', XenForo_Visitor::getInstance()->get('language_id'));

		$customFields = $this->_input->filterSingle('custom_fields', XenForo_Input::ARRAY_SIMPLE);
		$customFieldsShown = $this->_input->filterSingle('custom_fields_shown', XenForo_Input::STRING, array('array' => true));
		$writer->setCustomFields($customFields, $customFieldsShown);

		$writer->advanceRegistrationUserState(false);
		$writer->preSave();

		// TODO: option for extra user group

		$writer->save();
		$user = $writer->getMergedData();

		$userExternalModel->updateExternalAuthAssociation(
			$userExternalModel->bdApiConsumer_getProviderCode($producer),
			$externalVisitor['user_id'],
			$user['user_id'],
			$userExternalModel->bdApiConsumer_getUserProfileField()
		);

		XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');

		XenForo_Application::get('session')->changeUserId($user['user_id']);
		XenForo_Visitor::setup($user['user_id']);

		$redirect = $this->_input->filterSingle('redirect', XenForo_Input::STRING);

		$viewParams = array(
			'user' => $user,
			'redirect' => ($redirect ? XenForo_Link::convertUriToAbsoluteUri($redirect) : ''),
			'facebook' => true
		);

		return $this->responseView(
			'XenForo_ViewPublic_Register_Process',
			'register_process',
			$viewParams,
			$this->_getRegistrationContainerParams()
		);
	}
}