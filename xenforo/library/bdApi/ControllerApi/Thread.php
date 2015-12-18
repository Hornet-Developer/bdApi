<?php

class bdApi_ControllerApi_Thread extends bdApi_ControllerApi_Abstract
{
    public function actionGetIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        if (!empty($threadId)) {
            return $this->responseReroute(__CLASS__, 'single');
        }

        $threadIds = $this->_input->filterSingle('thread_ids', XenForo_Input::STRING);
        if (!empty($threadIds)) {
            return $this->responseReroute(__CLASS__, 'multiple');
        }

        $forumIdInput = $this->_input->filterSingle('forum_id', XenForo_Input::STRING);
        $sticky = $this->_input->filterSingle('sticky', XenForo_Input::STRING);
        $order = $this->_input->filterSingle('order', XenForo_Input::STRING, array('default' => 'natural'));

        if (strlen($forumIdInput) === 0) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'), 400);
        }
        $forumIdInput = explode(',', $forumIdInput);
        $forumIdInput = array_map('intval', $forumIdInput);

        $forumIdArray = array();
        $viewableNodes = $this->_getNodeModel()->getViewableNodeList();
        if (in_array(0, $forumIdInput, true)) {
            // accept 0 as a valid forum id
            // TODO: support `child_forums` param
            $forumIdArray[] = 0;
        }
        foreach ($viewableNodes as $viewableNode) {
            $viewableNode['node_id'] = intval($viewableNode['node_id']);
            if (in_array($viewableNode['node_id'], $forumIdInput, true)) {
                $forumIdArray[] = $viewableNode['node_id'];
            }
        }
        if (empty($forumIdArray)) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_requires_forum_id'), 400);
        }
        $forumIdArray = array_unique($forumIdArray);
        asort($forumIdArray);

        $pageNavParams = array('forum_id' => implode(',', $forumIdArray));
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->discussionsPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        $conditions = array(
            'deleted' => false,
            'moderated' => false,
            'node_id' => $forumIdArray,
            'sticky' => (intval($sticky) > 0),
        );
        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );

        switch ($order) {
            case 'thread_create_date':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_create_date_reverse':
                $fetchOptions['order'] = 'post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_update_date':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_update_date_reverse':
                $fetchOptions['order'] = 'last_post_date';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_view_count':
                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_view_count_reverse':
                $fetchOptions['order'] = 'view_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count':
                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'asc';
                $pageNavParams['order'] = $order;
                break;
            case 'thread_post_count_reverse':
                $fetchOptions['order'] = 'reply_count';
                $fetchOptions['orderDirection'] = 'desc';
                $pageNavParams['order'] = $order;
                break;
        }

        $threads = $this->_getThreadModel()->getThreads($conditions, $this->_getThreadModel()->getFetchOptionsToPrepareApiData($fetchOptions));

        if (!is_numeric($sticky) && intval($page) <= 1) {
            // mixed mode, put sticky threads on top of result if this is the first page
            // mixed mode is the active mode by default (no `sticky` param)
            // the two other modes related are: sticky mode (`sticky`=1) and non-sticky mode (`sticky`=0)
            $stickyThreads = $this->_getThreadModel()->getThreads(
                array_merge($conditions, array('sticky' => 1)),
                array_merge($fetchOptions, array(
                    'limit' => 0,
                    'page' => 0,
                ))
            );
            $threads = array_merge($stickyThreads, $threads);
        }

        $threadsData = $this->_prepareThreads($threads);

        $total = $this->_getThreadModel()->countThreads($conditions);

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
            'threads_total' => $total,
        );

        if (!$this->_isFieldExcluded('forum') AND count($forumIdArray) == 1) {
            $forumModel = $this->_getForumModel();
            $forum = $forumModel->getForumById(reset($forumIdArray), $forumModel->getFetchOptionsToPrepareApiData());
            if (!empty($forum)) {
                $data['forum'] = $this->_filterDataSingle($forumModel->prepareApiDataForForum($forum), array('forum'));
            }
        }

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'threads', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Thread_List', $data);
    }

    public function actionSingle()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        list($thread,) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable(
            $threadId,
            $this->_getThreadModel()->getFetchOptionsToPrepareApiData()
        );

        $threads = array($threadId => $thread);
        $threadsData = $this->_prepareThreads($threads);

        $data = array('thread' => $this->_filterDataSingle(reset($threadsData)));

        return $this->responseData('bdApi_ViewApi_Thread_Single', $data);
    }

    public function actionMultiple()
    {
        $threadIdsInput = $this->_input->filterSingle('thread_ids', XenForo_Input::STRING);
        $threadIds = array_map('intval', explode(',', $threadIdsInput));
        if (empty($threadIds)) {
            return $this->responseNoPermission();
        }

        $threads = $this->_getThreadModel()->getThreadsByIds(
            $threadIds,
            $this->_getThreadModel()->getFetchOptionsToPrepareApiData()
        );

        $threadsOrdered = array();
        foreach ($threadIds as $threadId) {
            if (isset($threads[$threadId])) {
                $threadsOrdered[$threadId] = $threads[$threadId];
            }
        }

        $threadsData = $this->_prepareThreads($threadsOrdered);

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
        );

        return $this->responseData('bdApi_ViewApi_Thread_List', $data);
    }

    public function actionPostIndex()
    {
        $input = $this->_input->filter(array(
            'forum_id' => XenForo_Input::UINT,
            'thread_title' => XenForo_Input::STRING,
            'thread_tags' => XenForo_Input::STRING,
        ));

        /* @var $editorHelper XenForo_ControllerHelper_Editor */
        $editorHelper = $this->getHelper('Editor');
        $input['post_body'] = $editorHelper->getMessageText('post_body', $this->_input);
        $input['post_body'] = XenForo_Helper_String::autoLinkBbCode($input['post_body']);

        $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($input['forum_id']);

        $visitor = XenForo_Visitor::getInstance();

        if (!$this->_getForumModel()->canPostThreadInForum($forum, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        /* @var $writer XenForo_DataWriter_Discussion_Thread */
        $writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');

        // note: assumes that the message dw will pick up the username issues
        $writer->bulkSet(array(
            'user_id' => $visitor['user_id'],
            'username' => $visitor['username'],
            'title' => $input['thread_title'],
            'node_id' => $forum['node_id'],
        ));

        // discussion state changes instead of first message state
        $writer->set('discussion_state', $this->_getPostModel()->getPostInsertMessageState(array(), $forum));

        $postWriter = $writer->getFirstMessageDw();
        $postWriter->set('message', $input['post_body']);
        $postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $this->_getAttachmentHelper()->getAttachmentTempHash($forum));
        $postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);

        /* @var $session bdApi_Session */
        $session = XenForo_Application::getSession();
        $clientId = $session->getOAuthClientId();
        if (!empty($clientId)) {
            $postWriter->set('bdapi_origin', $clientId);
        }

        $writer->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);

        $tagger = null;
        if (XenForo_Application::$versionId > 1050000
            && $this->_getThreadModel()->canEditTags(null, $forum)
        ) {
            // thread tagging is available since XenForo 1.5.0
            /** @var XenForo_Model_Tag $tagModel */
            $tagModel = $this->getModelFromCache('XenForo_Model_Tag');
            $tagger = $tagModel->getTagger('thread');
            $tagger->setPermissionsFromContext($forum)
                ->setTags($tagModel->splitTags($input['thread_tags']));
            $writer->mergeErrors($tagger->getErrors());
        }

        if ($writer->get('discussion_state') == 'visible') {
            switch ($this->_spamCheck(array(
                'content_type' => 'thread',
                'content' => $input['thread_title'] . "\n" . $input['post_body'],
            ))) {
                case XenForo_Model_SpamPrevention::RESULT_MODERATED:
                    $writer->set('discussion_state', 'moderated');
                    break;
                case XenForo_Model_SpamPrevention::RESULT_DENIED;
                    return $this->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later'), 400);
                    break;
            }
        }

        $writer->preSave();

        if ($writer->hasErrors()) {
            return $this->responseErrors($writer->getErrors(), 400);
        }

        $this->assertNotFlooding('post');

        $writer->save();

        $thread = $writer->getMergedData();

        if (!empty($tagger)) {
            $tagger->setContent($thread['thread_id'], true)
                ->save();
        }

        $this->_getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], array(
            // TODO
            'watch_thread_state' => 0,
            'watch_thread' => 0,
            'watch_thread_email' => 0,
        ));

        $this->_getThreadModel()->markThreadRead($thread, $forum, XenForo_Application::$time);

        $this->_request->setParam('thread_id', $thread['thread_id']);
        return $this->responseReroute(__CLASS__, 'single');
    }

    public function actionPutIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        $thread = $this->_getThreadModel()->getThreadById($threadId);
        if (empty($thread)) {
            return $this->responseNoPermission();
        }

        $this->_request->setParam('post_id', $thread['first_post_id']);
        return $this->responseReroute('bdApi_ControllerApi_Post', 'put-index');
    }

    public function actionDeleteIndex()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $deleteType = 'soft';
        $options = array('reason' => '[bd] API');

        if (!$this->_getThreadModel()->canDeleteThread($thread, $forum, $deleteType, $errorPhraseKey)) {
            throw $this->getErrorOrNoPermissionResponseException($errorPhraseKey);
        }

        $this->_getThreadModel()->deleteThread($thread['thread_id'], $deleteType, $options);

        XenForo_Model_Log::logModeratorAction('thread', $thread, 'delete_' . $deleteType, array('reason' => $options['reason']));

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostAttachments()
    {
        $contentData = $this->_input->filter(array(
            'forum_id' => XenForo_Input::UINT,
        ));
        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        $response = $attachmentHelper->doUpload('file', $hash, 'post', $contentData);

        if ($response instanceof XenForo_ControllerResponse_Abstract) {
            return $response;
        }

        $contentData['post_id'] = 0;
        $data = array('attachment' => $this->_getPostModel()->prepareApiDataForAttachment($response, $contentData, $contentData, $contentData, $hash));

        return $this->responseData('bdApi_ViewApi_Thread_Attachments', $data);
    }

    public function actionDeleteAttachments()
    {
        $contentData = $this->_input->filter(array('forum_id' => XenForo_Input::UINT));
        $attachmentId = $this->_input->filterSingle('attachment_id', XenForo_Input::UINT);

        if (empty($contentData['forum_id'])) {
            return $this->responseError(new XenForo_Phrase('bdapi_slash_threads_attachments_requires_forum_id'), 400);
        }

        $attachmentHelper = $this->_getAttachmentHelper();
        $hash = $attachmentHelper->getAttachmentTempHash($contentData);
        return $attachmentHelper->doDelete($hash, $attachmentId);
    }

    public function actionGetFollowed()
    {
        $this->_assertRegistrationRequired();

        $total = $this->_getThreadWatchModel()->countThreadsWatchedByUser(XenForo_Visitor::getUserId());
        if ($this->_input->inRequest('total')) {
            $data = array('threads_total' => $total);
            return $this->responseData('bdApi_ViewApi_Thread_Followed_Total', $data);
        }

        $pageNavParams = array();
        $page = $this->_input->filterSingle('page', XenForo_Input::UINT);
        $limit = XenForo_Application::get('options')->discussionsPerPage;

        $inputLimit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        if (!empty($inputLimit)) {
            $limit = $inputLimit;
            $pageNavParams['limit'] = $inputLimit;
        }

        $fetchOptions = array(
            'limit' => $limit,
            'page' => $page
        );

        $threadWatches = $this->_getThreadWatchModel()->getThreadsWatchedByUser(XenForo_Visitor::getUserId(), false, $fetchOptions);
        $threadsData = array();
        $threads = array();

        if (!empty($threadWatches)) {
            $threadIds = array();
            foreach ($threadWatches as $threadWatch) {
                $threadIds[] = $threadWatch['thread_id'];
            }

            $fetchOptions = $this->_getThreadModel()->getFetchOptionsToPrepareApiData();
            $threads = $this->_getThreadModel()->getThreadsByIds($threadIds, $fetchOptions);
            $threads = $this->_prepareThreads($threads);
        }

        foreach ($threadWatches as $threadWatch) {
            foreach ($threads as &$threadData) {
                if ($threadWatch['thread_id'] == $threadData['thread_id']) {
                    $threadData = $this->_getThreadWatchModel()->prepareApiDataForThreadWatches($threadData, $threadWatch);
                    $threadsData[] = $threadData;
                }
            }
        }

        $data = array(
            'threads' => $this->_filterDataMany($threadsData),
            'threads_total' => $total,
        );

        bdApi_Data_Helper_Core::addPageLinks($this->getInput(), $data, $limit, $total, $page, 'threads/followed', array(), $pageNavParams);

        return $this->responseData('bdApi_ViewApi_Thread_Followed', $data);
    }

    public function actionGetFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        $users = array();

        if ($this->_getThreadModel()->canWatchThread($thread, $forum)) {
            $visitor = XenForo_Visitor::getInstance();

            /* @var $threadWatchModel bdApi_Extend_Model_ThreadWatch */
            $threadWatchModel = $this->getModelFromCache('XenForo_Model_ThreadWatch');
            $threadWatch = $threadWatchModel->getUserThreadWatchByThreadId($visitor['user_id'], $thread['thread_id']);

            if (!empty($threadWatch)) {
                $user = array(
                    'user_id' => $visitor['user_id'],
                    'username' => $visitor['username'],
                );

                $user = $threadWatchModel->prepareApiDataForThreadWatches($user, $threadWatch);

                $users[] = $user;
            }
        }

        $data = array('users' => $this->_filterDataMany($users));

        return $this->responseData('bdApi_ViewApi_Thread_Followers', $data);
    }

    public function actionPostFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);
        $email = $this->_input->filterSingle('email', XenForo_Input::UINT);

        list($thread, $forum) = $this->_getForumThreadPostHelper()->assertThreadValidAndViewable($threadId);

        if (!$this->_getThreadModel()->canWatchThread($thread, $forum)) {
            return $this->responseNoPermission();
        }

        $state = ($email > 0 ? 'watch_email' : 'watch_no_email');
        $this->_getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $thread['thread_id'], $state);

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionDeleteFollowers()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        $this->_getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $threadId, '');

        return $this->responseMessage(new XenForo_Phrase('changes_saved'));
    }

    public function actionPostPollVotes()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $pollModel = $this->_getPollModel();
        $poll = $pollModel->getPollByContent('thread', $threadId);
        if (empty($poll)
            || !$this->_getThreadModel()->canVoteOnPoll($poll, $thread, $forum)
        ) {
            return $this->responseNoPermission();
        }

        return $pollModel->bdApi_actionPostVotes($poll, $this);
    }

    public function actionGetPollResults()
    {
        $threadId = $this->_input->filterSingle('thread_id', XenForo_Input::UINT);

        /** @var XenForo_ControllerHelper_ForumThreadPost $ftpHelper */
        $ftpHelper = $this->getHelper('ForumThreadPost');
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);

        $pollModel = $this->_getPollModel();
        $poll = $pollModel->getPollByContent('thread', $threadId);
        if (empty($poll)) {
            return $this->responseError(new XenForo_Phrase('requested_page_not_found'), 404);
        }

        return $pollModel->bdApi_actionGetResults($poll,
            $this->_getThreadModel()->canVoteOnPoll($poll, $thread, $forum), $this);
    }

    public function actionGetNew()
    {
        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);

        $this->_assertRegistrationRequired();

        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        if (empty($forumId)) {
            $threadIds = $threadModel->getUnreadThreadIds($visitor->get('user_id'), array('limit' => $maxResults));
        } else {
            $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);

            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forum['node_id'])));

            $threadIds = $threadModel->bdApi_getUnreadThreadIdsInForum($visitor->get('user_id'), array_merge(array($forum['node_id']), $childNodeIds), array('limit' => $maxResults));
        }

        return $this->_getNewOrRecentResponse($threadIds);
    }

    public function actionGetRecent()
    {
        $threadModel = $this->_getThreadModel();

        $days = $this->_input->filterSingle('days', XenForo_Input::UINT);
        if ($days < 1) {
            $days = max(7, XenForo_Application::get('options')->readMarkingDataLifetime);
        }

        $limit = $this->_input->filterSingle('limit', XenForo_Input::UINT);
        $maxResults = XenForo_Application::getOptions()->get('maximumSearchResults');
        if ($limit > 0) {
            $maxResults = min($maxResults, $limit);
        }

        $conditions = array(
            'last_post_date' => array(
                '>',
                XenForo_Application::$time - 86400 * $days
            ),
            'deleted' => false,
            'moderated' => false,
            'find_new' => true,
        );

        $fetchOptions = array(
            'limit' => $maxResults,
            'order' => 'last_post_date',
            'orderDirection' => 'desc',
            'join' => XenForo_Model_Thread::FETCH_FORUM_OPTIONS,
        );

        $forumId = $this->_input->filterSingle('forum_id', XenForo_Input::UINT);
        if (!empty($forumId)) {
            $forum = $this->_getForumThreadPostHelper()->assertForumValidAndViewable($forumId);
            $childNodeIds = array_keys($this->_getNodeModel()->getChildNodesForNodeIds(array($forum['node_id'])));
            $conditions['node_id'] = array_merge(array($forum['node_id']), $childNodeIds);
        }

        $threadIds = array_keys($threadModel->getThreads($conditions, $fetchOptions));

        return $this->_getNewOrRecentResponse($threadIds);
    }

    protected function _prepareThreads(array $threads)
    {
        $forumIds = array();
        $forums = array();
        foreach ($threads as $thread) {
            $forumIds[$thread['node_id']] = true;
        }
        if (!empty($forumIds)) {
            $forums = $this->_getForumModel()->getForumsByIds(array_keys($forumIds));
        }

        $visitor = XenForo_Visitor::getInstance();
        $nodePermissions = $this->_getNodeModel()->getNodePermissionsForPermissionCombination();
        foreach ($nodePermissions as $nodeId => $permissions) {
            $visitor->setNodePermissions($nodeId, $permissions);
        }

        foreach (array_keys($threads) as $threadId) {
            if (!empty($forums[$threads[$threadId]['node_id']])) {
                $threads[$threadId]['forum'] = $forums[$threads[$threadId]['node_id']];
            } else {
                unset($threads[$threadId]);
                continue;
            }

            if (!$this->_getThreadModel()->canViewThreadAndContainer($threads[$threadId], $threads[$threadId]['forum'])) {
                unset($threads[$threadId]);
                continue;
            }
        }

        $firstPostIds = array();
        $lastPostIds = array();
        $pollThreadIds = array();
        foreach ($threads as $thread) {
            if (!$this->_isFieldExcluded('first_post')) {
                $firstPostIds[$thread['thread_id']] = $thread['first_post_id'];
            }

            if ($this->_isFieldIncluded('last_post')
                && (!isset($firstPostIds[$thread['thread_id']])
                    || $thread['last_post_id'] != $thread['first_post_id'])
            ) {
                $lastPostIds[$thread['thread_id']] = $thread['last_post_id'];
            }

            if (!$this->_isFieldExcluded('poll')
                && $thread['discussion_type'] === 'poll'
            ) {
                $pollThreadIds[] = $thread['thread_id'];
            }
        }

        $posts = array();
        if (!empty($firstPostIds)
            || !empty($lastPostIds)
        ) {
            $posts = $this->_getPostModel()->getPostsByIds(
                array_merge(array_values($firstPostIds), array_values($lastPostIds)),
                $this->_getPostModel()->getFetchOptionsToPrepareApiData());

            if ((!empty($firstPostIds) && !$this->_isFieldExcluded('first_post.attachments'))
                || (!empty($lastPostIds) && !$this->_isFieldExcluded('last_post.attachments'))
            ) {
                $posts = $this->_getPostModel()->getAndMergeAttachmentsIntoPosts($posts);
            }
        }

        if (!empty($pollThreadIds)) {
            $polls = $this->_getPollModel()->bdApi_getPollByContentIds('thread', $pollThreadIds);
            $this->_getThreadModel()->bdApi_setPolls($polls);
        }

        $threadsData = array();
        foreach ($threads as $threadId => $thread) {
            $firstPost = array();
            if (isset($firstPostIds[$threadId])
                && isset($posts[$thread['first_post_id']])
            ) {
                $firstPost = $posts[$thread['first_post_id']];
            }

            $threadData = $this->_getThreadModel()->prepareApiDataForThread($thread, $thread['forum'], $firstPost);

            if (isset($lastPostIds[$threadId])
                && isset($posts[$thread['last_post_id']])
            ) {
                $postModel = $this->_getPostModel();
                $threadData['last_post'] = $postModel->prepareApiDataForPost(
                    $posts[$thread['last_post_id']], $thread, $thread['forum']);
            }

            $threadsData[] = $threadData;
        }

        return $threadsData;
    }

    protected function _getNewOrRecentResponse(array $threadIds)
    {
        $visitor = XenForo_Visitor::getInstance();
        $threadModel = $this->_getThreadModel();

        $results = array();
        $threads = $threadModel->getThreadsByIds($threadIds, array(
            'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'permissionCombinationId' => $visitor['permission_combination_id'],
        ));
        foreach ($threadIds AS $threadId) {
            if (!isset($threads[$threadId]))
                continue;
            $threadRef = &$threads[$threadId];

            $threadRef['permissions'] = XenForo_Permission::unserializePermissions($threadRef['node_permission_cache']);

            if ($threadModel->canViewThreadAndContainer($threadRef, $threadRef, $null, $threadRef['permissions'])) {
                $results[] = array('thread_id' => $threadId);
            }
        }

        $data = array('threads' => $results);

        return $this->responseData('bdApi_ViewApi_Thread_NewOrRecent', $data);
    }

    /**
     * @return XenForo_Model_Node
     */
    protected function _getNodeModel()
    {
        return $this->getModelFromCache('XenForo_Model_Node');
    }

    /**
     * @return bdApi_Extend_Model_Forum
     */
    protected function _getForumModel()
    {
        return $this->getModelFromCache('XenForo_Model_Forum');
    }

    /**
     * @return bdApi_Extend_Model_Thread
     */
    protected function _getThreadModel()
    {
        return $this->getModelFromCache('XenForo_Model_Thread');
    }

    /**
     * @return bdApi_Extend_Model_Post
     */
    protected function _getPostModel()
    {
        return $this->getModelFromCache('XenForo_Model_Post');
    }

    /**
     * @return bdApi_Extend_Model_Poll
     */
    protected function _getPollModel()
    {
        return $this->getModelFromCache('XenForo_Model_Poll');
    }

    /**
     * @return bdApi_Extend_Model_ThreadWatch
     */
    protected function _getThreadWatchModel()
    {
        return $this->getModelFromCache('XenForo_Model_ThreadWatch');
    }

    /**
     * @return XenForo_ControllerHelper_ForumThreadPost
     */
    protected function _getForumThreadPostHelper()
    {
        return $this->getHelper('ForumThreadPost');
    }

    /**
     * @return bdApi_ControllerHelper_Attachment
     */
    protected function _getAttachmentHelper()
    {
        return $this->getHelper('bdApi_ControllerHelper_Attachment');
    }

    protected function _prepareSessionActivityForApi(&$controllerName, &$action, array &$params)
    {
        switch ($action) {
            case 'GetIndex':
                $forumId = $this->_request->getParam('forum_id');
                if (!empty($forumId)
                    && is_numeric($forumId)
                ) {

                    $params['node_id'] = $forumId;
                }
                $controllerName = 'XenForo_ControllerPublic_Forum';
                break;
            case 'Single':
                $controllerName = 'XenForo_ControllerPublic_Thread';
                break;
            case 'GetNew':
            case 'GetRecent':
                $controllerName = 'XenForo_ControllerPublic_FindNew';
                break;
            default:
                parent::_prepareSessionActivityForApi($controllerName, $action, $params);
        }
    }
}
