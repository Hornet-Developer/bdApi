<?php

class bdApi_ControllerApi_Search extends bdApi_ControllerApi_Abstract
{
	public function actionGetIndex()
	{
		$data = array(
				'links' => array(
						'posts' 			=> bdApi_Link::buildApiLink('search/posts'),
						'threads' 			=> bdApi_Link::buildApiLink('search/threads'),
				),
		);

		return $this->responseData('bdApi_ViewApi_Index', $data);
	}

	public function actionGetThreads()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
	}

	public function actionPostThreads()
	{
		$results = $this->_doSearch('thread');

		$this->_getThreadModel();
		$threads = bdApi_XenForo_Model_Thread::bdApi_getCachedThreads();
		$threads = array_values($threads);

		$forumNodeIds = array();
		foreach ($threads as $thread)
		{
			$forumNodeIds[] = $thread['node_id'];
		}
		$forums = $this->_getForumModel()->getForumsByIds($forumNodeIds);

		foreach ($threads as &$thread)
		{
			$thread = $this->_getThreadModel()->prepareApiDataForThread($thread, $forums[$thread['node_id']]);
		}

		$data = array(
				'threads' => $threads,
		);

		return $this->responseData('bdApi_ViewApi_Search_Threads', $data);
	}

	public function actionGetPosts()
	{
		return $this->responseError(new XenForo_Phrase('bdapi_slash_search_only_accepts_post_requests'), 400);
	}


	public function actionPostPosts()
	{
		$results = $this->_doSearch('post');

		$this->_getPostModel();
		$posts = bdApi_XenForo_Model_Post::bdApi_getCachedPosts();
		$posts = array_values($posts);

		$forumNodeIds = array();
		foreach ($posts as $post)
		{
			$forumNodeIds[] = $post['node_id'];
		}
		$forums = $this->_getForumModel()->getForumsByIds($forumNodeIds);

		foreach ($posts as &$post)
		{
			$post = $this->_getPostModel()->prepareApiDataForPost($post, $post, $forums[$post['node_id']]);
		}

		$data = array(
				'posts' => $posts,
		);

		return $this->responseData('bdApi_ViewApi_Search_Posts', $data);
	}

	public function _doSearch($contentType)
	{
		if (!XenForo_Visitor::getInstance()->canSearch())
		{
			throw $this->getNoPermissionResponseException();
		}

		$input = array();

		$input['keywords'] = $this->_input->filterSingle('q', XenForo_Input::STRING);
		$input['keywords'] = XenForo_Helper_String::censorString($input['keywords'], null, ''); // don't allow searching of censored stuff
		if (empty($input['keywords']))
		{
			throw $this->responseException($this->responseError(new XenForo_Phrase('bdapi_slash_search_requires_q'), 400));
		}

		$visitorUserId = XenForo_Visitor::getUserId();
		$searchModel = $this->_getSearchModel();

		$constraints = $searchModel->getGeneralConstraintsFromInput($input, $errors);
		if ($errors)
		{
			return $this->responseError($errors, 503);
		}

		$typeHandler = $searchModel->getSearchDataHandler($contentType);

		$searcher = new XenForo_Search_Searcher($searchModel);

		return $searcher->searchType($typeHandler, $input['keywords'], $constraints);
	}

	protected function _getScopeForAction($action)
	{
		return bdApi_Model_OAuth2::SCOPE_READ;
	}

	/**
	 * @return XenForo_Model_Search
	 */
	protected function _getSearchModel()
	{
		return $this->getModelFromCache('XenForo_Model_Search');
	}

	/**
	 * @return XenForo_Model_Post
	 */
	protected function _getPostModel()
	{
		return $this->getModelFromCache('XenForo_Model_Post');
	}

	/**
	 * @return XenForo_Model_Thread
	 */
	protected function _getThreadModel()
	{
		return $this->getModelFromCache('XenForo_Model_Thread');
	}

	/**
	 * @return XenForo_Model_Forum
	 */
	protected function _getForumModel()
	{
		return $this->getModelFromCache('XenForo_Model_Forum');
	}
}