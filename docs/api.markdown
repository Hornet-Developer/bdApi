# API Documents

## Authorization
The system follows OAuth2 specification [IETF draft v10](http://tools.ietf.org/html/draft-ietf-oauth-v2-10).

### Supported scopes
 * `read`
 * `post`
 * `usercp`
 * `conversate`
 * `admincp`

### Supported grant types
 * Authorization code
 * User credentials (username / password)
 * Refresh token

### Configuration
 * TTL of access token: 1 hour
 * TTL of authorization code: 30 seconds
 * TTL of refresh token: 2 weeks
 * Authorization URI: `/oauth/authorize`
 * Access token exchange URI: `/oauth/token`

## Common Parameters

### Fields filtering
For API method with resource data like a forum or a thread, the data can be filtered to get interested fields only. When there are no filter 

 * `fields_include`: coma-separated list of fields of a resource. If this parameter is used along with `fields_exclude`, the other parameter will be ignored.
 * `fields_exclude`: coma-separated list of fields of a resource to exclude in the response. Cannot be used with `fields_include` or this parameter will be ignored.

### Resource ordering
For API method with list of resources, the resources can be ordered differently with the parameter `order`. List of supported orders will be specified for each method. The default order will always be `natural`. Most of the time, the natural order is the order of which each resource is added to the system (resource id for example).

## Categories

### GET `/categories`
List of all categories in the system.

    {
        categories: [
            (category),
            ...
        ],
        categories_count: (int)
    }

Parameters:

 * `parent_category_id` (_optional_): id of parent category. If exists, filter categories that are direct children of that category.
 * `parent_forum_id` (_optional_): id of parent forum. If exists, filter categories that are direct children of that forum.
 * `order` (_optional_): ordering of categories. Support `natural`, `list`.

Required scopes:

 * `read`

### GET `/categories/:categoryId`
Detail information of a category.

    {
        category: {
            category_id: (int),
            category_title: (string),
            category_description: (string),
            links: {
                permalink: (uri),
                detail: (uri),
                sub-categories: (uri),
                sub-forums: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

## Forums

### GET `/forums`
List of all forums in the system.

    {
        forums: [
            (forum),
            ...
        ],
        forums_count: (int)
    }

Parameters:

 * `parent_category_id` (_optional_): id of parent category. If exists, filter forums that are direct children of that category.
 * `parent_forum_id` (_optional_): id of parent forum. If exists, filter forums that are direct children of that forum.
 * `order` (_optional_): ordering of forums. Support `natural`, `list`.

Required scopes:

 * `read`

### GET `/forums/:forumId`
Detail information of a category.

    {
        forum: {
            forum_id: (int),
            forum_title: (string),
            forum_description: (string),
            forum_thread_count: (int),
            forum_post_count: (int),
            links: {
                permalink: (uri),
                detail: (uri),
                sub-categories: (uri),
                sub-forums: (uri),
                threads: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                create_thread: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

## Threads

### GET `/threads`
List of threads in a forum (with pagination).

    {
        threads: [
            (thread),
            ...
        ],
        threads_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `forum_id` (__required__): id of needed forum.
 * `page` (_optional_): page number of threads.
 * `limit` (_optional_): number of threads in a page. Default value depends on the system configuration.
 * `order` (_optional_): ordering of threads. Support `natural`, `thread_create_date`, `thread_create_date_reverse`, `thread_update_date`, `thread_update_date_reverse`.

Required scopes:

 * `read`

### POST `/threads`
Create a new thread.

    {
        thread: (thread)
    }

Parameters:

 * `forum_id` (__required__): id of the target forum.
 * `thread_title` (__required__): title of the new thread.
 * `post_body` (__required__): content of the new thread.

Required scopes:

 * `post`

### POST `/threads/attachments`
Upload an attachment for a thread.

    {
        attachment: (post > attachment)
    }

Parameters:

* `file` (__required__): binary data of the attachment.
* `forum_id` (__required__): id of the container forum of the target thread.

Required scopes:

* `post`

### GET `/threads/:threadId`
Detail information of a thread.

    {
        thread: {
            thread_id: (int),
            forum_id: (int),
            thread_title: (string),
            thread_view_count: (int),
            thread_post_count: (int),
            creator_user_id: (int),
            creator_username: (string),
            thread_create_date: (unix timestamp in seconds),
            thread_update_date: (unix timestamp in seconds),
            thread_is_published: (boolean),
            thread_is_deleted: (boolean),
            thread_is_sticky: (boolean),
            first_post: (post),
            links: {
                permalink: (uri),
                detail: (uri),
                forum: (uri),
                posts: (uri),
                first_poster: (uri),
                first_post: (uri),
                last_poster: (uri),
                last_post: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                post: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### DELETE `/threads/:threadId`
Delete a thread.

    {
        status: "ok",
        message: "Thread #x has been deleted."
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/threads/new`
List of unread threads (must be logged in).

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ]
    }

Parameters:

 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

Required scopes:

 * `read`

### GET `/threads/recent`
List of recent threads.

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ]
    }

Parameters:

 * `days` (_optional_): maximum number of days to search for threads.
 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

Required scopes:

 * `read`

## Posts

### GET `/posts`
List of posts in a thread (with pagination).

    {
        posts: [
            (post),
            ...
        ],
        posts_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `thread_id` (__required__): id of needed thread.
 * `page` (_optional_): page number of posts.
 * `limit` (_optional_): number of threads in a page. Default value depends on the system configuration.

Required scopes:

 * `read`

### POST `/posts`
Create a new post.

    {
        post: (post)
    }

Parameters:

 * `thread_id` (__required__): id of the target thread.
 * `post_body` (__required__): content of the new post.

Required scopes:

 * `post`

### POST `/posts/attachments`
Upload an attachment for a post. The attachment will be associated after the post is saved.

    {
        attachment: (post > attachment)
    }

Parameters:

 * `file` (__required__): binary data of the attachment.
 * `thread_id` (_optional_): id of the container thread of the target post.
 * `post_id` (_optional_): id of the target post.

Parameters Note: either `thread_id` or `post_id` parameter must has a valid id. Simply speaking, `thread_id` must be used with POST `/posts` (creating a new post) while `post_id` must be used with PUT `/posts/:postId` (editing a post).

Required scopes:

* `post`

### GET `/posts/:postId`
Detail information of a post.

    {
        post: {
            post_id: (int),
            thread_id: (int),
            poster_user_id: (int),
            poster_username: (string),
            post_create_date: (unix timestamp in seconds),
            post_body: (string),
            post_body_html: (string),
            post_body_plain_text: (string),
            post_like_count: (int),
            post_attachment_count: (int),
            post_is_published: (boolean),
            post_is_deleted: (boolean),
            post_is_liked: (boolean),
            attachments: {
                {
                    attachment_id: (int),
                    post_id: (int),
                    attachment_download_count: (int),
                    links: {
                        permalink: (uri),
                        thumbnail: (uri)
                    },
                    permissions: {
                        view: (boolean)
                    }
                },
                ...
            },
            links: {
                permalink: (uri),
                detail: (uri),
                thread: (uri),
                poster: (uri),
                likes: (uri)
            },
            permissions: {
                view: (boolean),
                edit: (boolean),
                delete: (boolean),
                like: (boolean)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### PUT `/posts/:postId`
Edit a post.

    {
        post: (post)
    }

Parameters:

 * `post_body` (__required__): new content of the post.

Required scopes:

 * `post`

### DELETE `/posts/:postId`
Delete a post.

    {
        status: "ok",
        message: "Post #x has been deleted."
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/posts/:postId/likes`
List of users who liked a post.

    {
        users: [
            {
                user_id: (int),
                username: (string)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `/posts/:postId/likes`
Like a post.

    {
        status: "ok",
        message: "Post #x has been liked."
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### DELETE `/posts/:postId/likes`
Unlike a post.

    {
        status: "ok",
        message: "Post #x has been unliked."
    }

Parameters:

 * N/A

Required scopes:

 * `post`

## Users

### GET `/users`
List of users (with pagination).

    {
        users: [
            (user),
            (user),
            ...
        ],
        users_total: (int),
        links: {
            pages: (int),
            next: (uri),
            prev: (uri)
        }
    }

Parameters:

 * `page` (_optional_): page number of users.
 * `limit` (_optional_): number of users in a page. Default value depends on the system configuration.

Required scopes:

 * `read`

### POST `/users`
Create a new user.

    {
        user: (user)
    }

Parameters:

 * `email` (__required__): email of the new user.
 * `username` (__required__): username of the new user.
 * `password` (__required__): password of the new user.
 * `user_dob_day` (_optional_): date of birth (day) of the new user.
 * `user_dob_month` (_optional_): date of birth (month) of the new user.
 * `user_dob_year` (_optional_): date of birth (year) of the new user.

Required scopes:

 * `post`

### GET `/users/:userId`
Detail information of a user.

    {
        user: {
            user_id: (int),
            username: (string),
            user_title: (string),
            user_message_count: (int),
            user_register_date: (unix timestamp in seconds),
            user_like_count: (int),
            user_email: (email),
            user_dob_day: (int),
            user_dob_month: (int),
            user_dob_year: (int),
            user_timezone_offset: (int),
            user_is_valid: (boolean),
            user_is_verified: (boolean),
            links: {
                permalink: (uri),
                detail: (uri),
                avatar: (uri),
                followers: (uri),
                followings: (uri)
            }
        }
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `users/:userId/avatar`
Upload avatar for a user.

    {
        status: "ok",
        message: "Upload completed successfully"
    }

Parameters:

 * avatar (__required__): binary data of the avatar.

Required scopes:

 * `post`

### DELETE `users/:userId/avatar`
Delete avatar for a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/users/:userId/followers`
List of a user's followers

    {
        followers: [
            {
                user_id: (int)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### POST `users/:userId/followers`
Follow a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### DELETE `users/:userId/followers`
Un-follow a user.

    {
        status: "ok",
        message: "Changes Saved"
    }

Parameters:

 * N/A

Required scopes:

 * `post`

### GET `/users/:userId/followings`
List of users whom are followed by a user.

    {
        followings: [
            {
                user_id: (int)
            },
            ...
        ]
    }

Parameters:

 * N/A

Required scopes:

 * `read`

### GET `/users/me`
Alias for GET `/users/:userId` for authorized user.

### POST `/users/me/avatar`
Alias for POST `/users/:userId/avatar` for authorized user.

### DELETE `/users/me/avatar`
Alias for DELETE `/users/:userId/avatar` for authorized user.

### GET `/users/me/followers`
Alias for GET `/users/:userId/followers` for authorized user.

### GET `/users/me/followings`
Alias for GET `/users/:userId/followings` for authorized user.

## Searching

### POST `/search/threads`
Search for threads.

    {
        threads: [
            {
                thread_id: (int)
            },
            ...
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of result threads. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for threads. Child forums of the specified forum will be included in the search.

Required scopes:

 * `read`

### POST `/search/posts`
Search for posts.

    {
        posts: [
            {
                post_id: (int)
            },
            ...
        ]
    }

Parameters:

 * `q` (__required__): query to search for.
 * `limit` (_optional_): maximum number of result posts. The limit may get decreased if the value is too large (depending on the system configuration).
 * `forum_id` (_optional_): id of the container forum to search for posts. Child forums of the specified forum will be included in the search.
 * `thread_id` (_optional_): id of the container thread to search for posts.

Required scopes:

 * `read`

## Batch requests

### POST `/batch`
Execute multiple API requests at once.

    {
        jobs: {
            (job_id): {
                _job_result: (ok|error|message),
                _job_error: (string),
                _job_message: (string),
                ...
            },
            ...
        }
    }

JSON POST body:

    [
        {
            id: (string),
            uri: (uri),
            method: (DELETE|GET|POST|PUT),
            params: {
                (key): (value),
                ...
            }
        },
        ...
    ]

Parameters (for a single job):

 * `id` (_optional_): identifier for the job, will be use in output as key of a result set. If this parameter is not set, the URI will be used.
 * `uri` (__required__): URI of the API request to execute.
 * `method` (_optional_): HTTP method of the API request to execute. If this parameter is not set, GET HTTP method will be used.
 * `params` (_optional_): parameters of the API request to execute.

Required scopes:

 * N/A
