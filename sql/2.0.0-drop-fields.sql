ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_added_user_text;
ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_edited_user_text;
ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_deleted;
ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_deleted_user;
ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_deleted_user_ip;
ALTER TABLE /*_*/wikiforum_category DROP COLUMN wfc_deleted_user_text;

ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_last_post_user_text;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_added_user_text;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_edited_user_text;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_deleted;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_deleted_user;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_deleted_user_ip;
ALTER TABLE /*_*/wikiforum_forums DROP COLUMN wff_deleted_user_text;

ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_user_text;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_deleted;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_deleted_user;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_deleted_user_ip;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_deleted_user_text;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_edit_user_text;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_closed_user_text;
ALTER TABLE /*_*/wikiforum_threads DROP COLUMN wft_last_post_user_text;

ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_user_text;
ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_deleted;
ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_deleted_user;
ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_deleted_user_ip;
ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_deleted_user_text;
ALTER TABLE /*_*/wikiforum_replies DROP COLUMN wfr_edit_user_text;