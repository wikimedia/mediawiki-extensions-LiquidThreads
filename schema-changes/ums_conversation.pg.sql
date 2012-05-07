-- ums_conversation
-- Adds and populates the ums_conversation field, along with relevant indices.
ALTER TABLE /*_*/user_message_state ADD COLUMN ums_conversation int NOT NULL DEFAULT 0;

CREATE INDEX /*i*/ums_user_conversation ON /*_*/user_message_state (ums_user,ums_conversation);
DROP INDEX IF EXISTS /*i*/ums_user_read;

UPDATE /*_*/user_message_state
SET /*_*/ums_conversation = coalesce(/*_*/thread.thread_ancestor, /*_*/thread.thread_id)
FROM /*_*/thread
WHERE ums_conversation = 0 AND /*_*/thread.thread_id = /*_*/user_message_state.ums_thread;
