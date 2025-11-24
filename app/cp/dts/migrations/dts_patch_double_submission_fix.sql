-- ========================================
-- DTS Double Submission Defense Patch
-- ========================================
-- Purpose: Add unique constraint to prevent duplicate events
-- Date: 2025-02-23
-- ========================================

-- Add UNIQUE KEY to cp_dts_event
-- This prevents concurrent requests from creating duplicate events for the same object, type, and date.
-- Note: 'is_deleted' is included to allow re-creating an event if the previous one was soft-deleted.

ALTER TABLE `cp_dts_event`
ADD UNIQUE KEY `uk_event_duplicate` (`object_id`, `event_type`, `event_date`, `is_deleted`);
