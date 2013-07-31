-- The 1.3.0-SW bugfix release introduced a lot of *_user_text and *_user_ip
-- columns all over the place, for storing the user's username and IP address,
-- respectively.
ALTER TABLE /*_*/wikiforum_category ADD wfc_added_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_category ADD wfc_added_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_category ADD wfc_edited_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_category ADD wfc_edited_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_category ADD wfc_deleted_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_category ADD wfc_deleted_user_ip varchar(255) NOT NULL DEFAULT '';

ALTER TABLE /*_*/wikiforum_forums ADD wff_last_post_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_last_post_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_added_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_added_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_edited_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_edited_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_deleted_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_forums ADD wff_deleted_user_ip varchar(255) NOT NULL DEFAULT '';

ALTER TABLE /*_*/wikiforum_threads ADD wft_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_deleted_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_deleted_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_edit_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_edit_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_closed_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_closed_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_last_post_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_threads ADD wft_last_post_user_ip varchar(255) NOT NULL DEFAULT '';

ALTER TABLE /*_*/wikiforum_replies ADD wfr_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_replies ADD wfr_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_replies ADD wfr_deleted_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_replies ADD wfr_deleted_user_ip varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_replies ADD wfr_edit_user_text varchar(255) NOT NULL DEFAULT '';
ALTER TABLE /*_*/wikiforum_replies ADD wfr_edit_user_ip varchar(255) NOT NULL DEFAULT '';