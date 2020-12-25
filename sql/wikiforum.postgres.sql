DROP SEQUENCE IF EXISTS wikiforum_category_wfc_category_seq CASCADE;
CREATE SEQUENCE wikiforum_category_wfc_category_seq;

CREATE TABLE IF NOT EXISTS wikiforum_category (
	wfc_category INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('wikiforum_category_wfc_category_seq'),
	wfc_category_name TEXT NOT NULL,
	wfc_sortkey INTEGER NOT NULL DEFAULT 9,
	wfc_added_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wfc_added_actor INTEGER NULL DEFAULT NULL,
	wfc_added_user_ip TEXT NOT NULL DEFAULT '',
	wfc_edited_timestamp TIMESTAMPTZ NULL default NULL,
	wfc_edited_actor INTEGER NULL DEFAULT NULL,
	wfc_edited_user_ip TEXT NOT NULL DEFAULT ''
);

ALTER SEQUENCE wikiforum_category_wfc_category_seq OWNED BY wikiforum_category.wfc_category;

DROP SEQUENCE IF EXISTS wikiforum_forums_wff_forum_seq CASCADE;
CREATE SEQUENCE wikiforum_forums_wff_forum_seq;

CREATE TABLE IF NOT EXISTS wikiforum_forums (
	wff_forum INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('wikiforum_forums_wff_forum_seq'),
	wff_forum_name TEXT NOT NULL,
	wff_description TEXT NOT NULL,
	wff_category INTEGER NOT NULL,
	wff_sortkey INTEGER NOT NULL DEFAULT 9,
	wff_thread_count INTEGER NOT NULL DEFAULT 0,
	wff_reply_count INTEGER NOT NULL DEFAULT 0,
	wff_last_post_actor INTEGER NULL DEFAULT NULL,
	wff_last_post_user_ip TEXT NOT NULL DEFAULT '',
	wff_last_post_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wff_added_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wff_added_actor INTEGER NULL DEFAULT NULL,
	wff_added_user_ip TEXT NOT NULL DEFAULT '',
	wff_edited_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wff_edited_actor INTEGER NULL DEFAULT NULL,
	wff_edited_user_ip TEXT NOT NULL DEFAULT '',
	wff_announcement SMALLINT NOT NULL DEFAULT 0
);

ALTER SEQUENCE wikiforum_forums_wff_forum_seq OWNED BY wikiforum_forums.wff_forum;

DROP SEQUENCE IF EXISTS wikiforum_threads_wft_thread_seq CASCADE;
CREATE SEQUENCE wikiforum_threads_wft_thread_seq;

CREATE TABLE IF NOT EXISTS wikiforum_threads (
	wft_thread INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('wikiforum_threads_wft_thread_seq'),
	wft_thread_name TEXT NOT NULL,
	wft_text TEXT NOT NULL,
	wft_sticky SMALLINT NOT NULL DEFAULT 0,
	wft_posted_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wft_actor INTEGER NULL DEFAULT NULL,
	wft_user_ip TEXT NOT NULL DEFAULT '',
	wft_edit_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wft_edit_actor INTEGER NULL DEFAULT NULL,
	wft_edit_user_ip TEXT NOT NULL DEFAULT '',
	wft_closed_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wft_closed_actor INTEGER NULL DEFAULT NULL,
	wft_closed_user_ip TEXT NOT NULL DEFAULT '',
	wft_forum INTEGER NOT NULL DEFAULT 0,
	wft_reply_count INTEGER NOT NULL DEFAULT 0,
	wft_view_count INTEGER NOT NULL DEFAULT 0,
	wft_last_post_actor INTEGER NULL DEFAULT NULL,
	wft_last_post_user_ip TEXT NOT NULL DEFAULT '',
	wft_last_post_timestamp TIMESTAMPTZ NOT NULL default NOW()
);

ALTER SEQUENCE wikiforum_threads_wft_thread_seq OWNED BY wikiforum_threads.wft_thread;

DROP SEQUENCE IF EXISTS wikiforum_replies_wfr_reply_id_seq CASCADE;
CREATE SEQUENCE wikiforum_replies_wfr_reply_id_seq;

CREATE TABLE IF NOT EXISTS wikiforum_replies (
	wfr_reply_id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('wikiforum_replies_wfr_reply_id_seq'),
	wfr_reply_text TEXT NOT NULL,
	wfr_posted_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wfr_actor INTEGER NULL DEFAULT NULL,
	wfr_user_ip TEXT NOT NULL DEFAULT '',
	wfr_edit_timestamp TIMESTAMPTZ NOT NULL default NOW(),
	wfr_edit_actor INTEGER NULL DEFAULT NULL,
	wfr_edit_user_ip TEXT NOT NULL DEFAULT '',
	wfr_thread INTEGER NOT NULL
);

ALTER SEQUENCE wikiforum_replies_wfr_reply_id_seq OWNED BY wikiforum_replies.wfr_reply_id;
