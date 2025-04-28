<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace core_grades;

use core\context;

/**
 * The grade penalty exemption class for creating and managing exemptions.
 *
 * @package     core_grades
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class penalty_exemption {
    /** @var string The item type for exempt users. */
    public const TYPE_USER = 'user';

    /** @var string The item type for exempt groups. */
    public const TYPE_GROUP = 'group';

    /** @var string The name of the table which exemptions are stored in. */
    private const EXEMPTION_TABLE = 'grade_penalty_exemptions';

    /** @var int $id The id of the exemption.*/
    private $id;

    /** @var int $timecreated The time at which the exemption was created.*/
    private $timecreated;

    /** @var int $timemodified The time at which the last modification of the exemption took place.*/
    private $timemodified;

    /** @var int|null $usercreated The id of the user who last updated the exemption.*/
    private $usermodified;

    /**
     * Exemption constructor.
     *
     * @param string $itemtype The type of item being marked as exempt (e.g. exemption::TYPE_USER).
     * @param int $itemid The id of the item that is being marked as exempt (e.g. user id or group id).
     * @param int $contextid The id of the context in which this exemption applies to.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason for the exemption.
     */
    private function __construct(
        /** @var string $itemtype The type of item being marked as exempt (e.g. exemption::TYPE_USER). */
        private string $itemtype,
        /** @var int $itemid The id of the item that is being marked as exempt (e.g. user id or group id). */
        private int $itemid,
        /** @var int $contextid The id of the context in which this exemption applies to. */
        private int $contextid,
        /** @var string|null $reason The reason for the exemption. */
        private ?string $reason = null,
        /** @var int|null $reasonformat The format of the reason for the exemption. */
        private ?int $reasonformat = null
    ) {
    }

    /**
     * Get the exemption id.
     *
     * @return int|null The exemption id.
     */
    public function get_id(): ?int {
        return $this->id;
    }

    /**
     * Get the exemption item type.
     *
     * @return string The exemption item type.
     */
    public function get_itemtype(): string {
        return $this->itemtype;
    }

    /**
     * Get the exemption item id.
     *
     * @return int The exemption item id.
     */
    public function get_itemid(): int {
        return $this->itemid;
    }

    /**
     * Get the exemption context id.
     *
     * @return int The exemption context id.
     */
    public function get_contextid(): int {
        return $this->contextid;
    }
    /**
     * Get the exemption time created.
     *
     * @return int The exemption time created.
     */
    public function get_timecreated(): int {
        return $this->timecreated;
    }

    /**
     * Get the exemption time modified.
     *
     * @return int The exemption time modified.
     */
    public function get_timemodified(): int {
        return $this->timemodified;
    }

    /**
     * Get the exemption user modified.
     *
     * @return int|null The exemption user modified.
     */
    public function get_usermodified(): ?int {
        return $this->usermodified;
    }

    /**
     * Get the exemption reason.
     *
     * @return string|null The exemption reason.
     */
    public function get_reason(): ?string {
        return $this->reason;
    }

    /**
     * Get the exemption reason format.
     *
     * @return int|null The exemption reason format.
     */
    public function get_reasonformat(): ?int {
        return $this->reasonformat;
    }

    /**
     * Set the exemption reason.
     *
     * @param string|null $reason The exemption reason.
     * @param int|null $reasonformat The exemption reason format.
     */
    public function set_reason(?string $reason, ?int $reasonformat): void {
        $this->reason = $reason;
        $this->reasonformat = $reasonformat;
    }

    /**
     * Save the exemption to the database.
     *
     * @param int|null $usermodified The user id of the user who last modified the exemption or the current user if null.
     *
     * @return penalty_exemption The updated exemption object.
     */
    public function save(?int $usermodified = null): penalty_exemption {
        global $DB, $USER;

        $this->validate();

        $time = time();
        $this->timemodified = $time;
        $this->usermodified = $usermodified ?? $USER->id;

        if (empty($this->id)) {
            $this->timecreated = $time;
            $this->id = $DB->insert_record(self::EXEMPTION_TABLE, $this->to_record());
            $this->recalculate_penalties();
            self::trigger_event(\core\event\grade_penalty_exemption_created::class);
        } else {
            $DB->update_record(self::EXEMPTION_TABLE, $this->to_record());
            self::trigger_event(\core\event\grade_penalty_exemption_updated::class);
        }

        // Technically redundant because the caller already has the object, but useful for chaining.
        return $this;
    }

    /**
     * Delete the exemption from the database.
     *
     * @return void
     */
    public function delete(): void {
        global $DB;

        $DB->delete_records(self::EXEMPTION_TABLE, ['id' => $this->id]);
        $this->recalculate_penalties();
        self::trigger_event(\core\event\grade_penalty_exemption_deleted::class);
        $this->id = null;
    }

    /**
     * Recalculate grade penalties for users affected by this exemption.
     *
     * @throws \moodle_exception If the exemption item type is invalid.
     */
    private function recalculate_penalties(): void {
        $context = context::instance_by_id($this->contextid);
        switch ($this->itemtype) {
            case self::TYPE_USER:
                penalty_manager::recalculate_penalty($context, [$this->itemid], $this->usermodified);
                break;

            case self::TYPE_GROUP:
                $groupmembers = self::get_group_members($this->itemid);
                if (!empty($groupmembers)) {
                    penalty_manager::recalculate_penalty($context, $groupmembers, $this->usermodified);
                }
                break;

            default:
                throw new \moodle_exception('invaliditemtype', 'core_grades');
        }
    }

    /**
     * Validate the exemption before saving.
     *
     * @throws \moodle_exception If the exemption is invalid.
     */
    private function validate(): void {
        switch ($this->itemtype) {
            case self::TYPE_USER:
                if (empty($this->itemid)) {
                    throw new \moodle_exception('exemptionmissinguserid', 'core_grades');
                }
                break;
            case self::TYPE_GROUP:
                if (empty($this->itemid)) {
                    throw new \moodle_exception('exemptionmissinggroupid', 'core_grades');
                }
                break;
            default:
                throw new \moodle_exception('invaliditemtype', 'core_grades');
        }
        if (empty($this->contextid)) {
            throw new \moodle_exception('exemptionmissingcontextid', 'core_grades');
        }
    }

    /**
     * Get the exemption record as a stdClass object.
     *
     * @return \stdClass The exemption record.
     */
    private function to_record(): \stdClass {
        return (object) [
            'id' => $this->id,
            'itemtype' => $this->itemtype,
            'itemid' => $this->itemid,
            'contextid' => $this->contextid,
            'reason' => $this->reason,
            'reasonformat' => $this->reasonformat,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
            'usermodified' => $this->usermodified,
        ];
    }

    /**
     * Exempt a user from grade penalties in the specified context.
     *
     * @param int $userid The exempt user id.
     * @param int $contextid The context id in which the exemption applies.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason.
     *
     * @return penalty_exemption
     */
    public static function exempt_user(
        int $userid,
        int $contextid,
        ?string $reason = null,
        ?int $reasonformat = null
    ): penalty_exemption {

        $exem = self::find_by([
            'itemtype' => self::TYPE_USER,
            'itemid' => $userid,
            'contextid' => $contextid,
        ]);

        if (empty($exem)) {
            $exem = new penalty_exemption(
                self::TYPE_USER,
                $userid,
                $contextid,
                $reason,
                $reasonformat
            );
        } else {
            $exem = reset($exem);
            $exem->reason = $reason;
            $exem->reasonformat = $reasonformat;
        }

        return $exem->save();
    }

    /**
     * Exempt a group from grade penalties in the specified context.
     *
     * @param int $groupid The exempt group id.
     * @param int $contextid The context id in which the exemption applies.
     * @param string|null $reason The reason for the exemption.
     * @param int|null $reasonformat The format of the reason.
     *
     * @return penalty_exemption
     */
    public static function exempt_group(
        int $groupid,
        int $contextid,
        ?string $reason = null,
        ?int $reasonformat = null
    ): penalty_exemption {
        $exem = self::find_by([
            'itemtype' => self::TYPE_GROUP,
            'itemid' => $groupid,
            'contextid' => $contextid,
        ]);

        if (empty($exem)) {
            $exem = new penalty_exemption(
                self::TYPE_GROUP,
                $groupid,
                $contextid,
                $reason,
                $reasonformat
            );
        } else {
            $exem = reset($exem);
            $exem->reason = $reason;
            $exem->reasonformat = $reasonformat;
        }

        return $exem->save();
    }

    /**
     * Check if the user is exempt from grade penalties.
     *
     * @param int $userid The user id to check.
     * @param int $contextid The context id to check.
     *
     * @return bool
     */
    public static function is_user_exempt(int $userid, int $contextid): bool {
        global $DB;

        $context = context::instance_by_id($contextid);
        $contextids = $context->get_parent_context_ids(true);

        if (empty($contextids)) {
            return false;
        }

        // Check if the user is exempt.
        if (self::user_exemption_exists($userid, $contextids)) {
            return true;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
        $inparams = array_merge($inparams, ['itemtype' => self::TYPE_GROUP, 'userid' => $userid]);
        $sql = "SELECT 1
                  FROM {grade_penalty_exemptions} gpe
                  JOIN {groups} g ON g.id = gpe.itemid
                  JOIN {groups_members} gm ON gm.groupid = g.id
                 WHERE gpe.itemtype = :itemtype AND gpe.contextid $insql AND gm.userid = :userid";
        return $DB->record_exists_sql($sql, $inparams);
    }

    /**
     * Check if the group is exempt from grade penalties.
     *
     * @param int $groupid The group id to check.
     * @param int $contextid The context id to check.
     *
     * @return bool
     */
    public static function is_group_exempt(int $groupid, int $contextid): bool {
        $context = context::instance_by_id($contextid);
        $contextids = $context->get_parent_context_ids(true);

        if (empty($contextids)) {
            return false;
        }

        // Check if the group is exempt.
        if (self::group_exemption_exists($groupid, $contextids)) {
            return true;
        }

        return false;
    }

    /**
     * Get an exemption by its id.
     *
     * @param int $id The exemption id.
     *
     * @return penalty_exemption|null The exemption object or null if not found.
     */
    public static function get(int $id): ?penalty_exemption {
        $result = self::find_by(['id' => $id]);
        return empty($result) ? null : reset($result);
    }

    /**
     * Return all exemptions that match the specified criteria.
     *
     * @param array $criteria Key/value pairs where keys are field names and values are scalars or arrays to match against.
     * @param int $limitfrom Optional pagination control for returning a subset starting from this record.
     * @param int $limitnum Optional pagination control for returning a subset of this many records.
     *
     * @return array The list of exemptions matching the criteria.
     * @throws \dml_exception If any database errors are encountered.
     */
    public static function find_by(array $criteria, int $limitfrom = 0, int $limitnum = 0): array {
        global $DB;

        [$select, $params] = self::get_select_params($criteria);
        $records = $DB->get_records_select(
            self::EXEMPTION_TABLE,
            $select,
            $params,
            '',
            '*',
            $limitfrom,
            $limitnum
        );
        return self::from_records($records);
    }

    /**
     * Return the number of exemptions matching the specified criteria.
     *
     * @param array $criteria Key/value pairs where keys are field names and values are scalars or arrays to match against.
     *
     * @return int The number of exemptions matching the criteria.
     */
    public static function count_by(array $criteria): int {
        global $DB;

        [$select, $params] = self::get_select_params($criteria);
        return $DB->count_records_select(
            self::EXEMPTION_TABLE,
            $select,
            $params
        );
    }

    /**
     * Check if the user is exempt from the grade penalty.
     *
     * @param int $userid The user id to check.
     * @param array $contextids The context ids to check.
     *
     * @return bool
     */
    private static function user_exemption_exists(int $userid, array $contextids): bool {
        $criteria = [
            'itemtype' => self::TYPE_USER,
            'itemid' => $userid,
            'contextid' => $contextids,
        ];
        return self::count_by($criteria) > 0;
    }

    /**
     * Check if the group is exempt from the grade penalty.
     *
     * @param int $groupid The group id to check.
     * @param array $contextids The context ids to check.
     *
     * @return bool
     */
    private static function group_exemption_exists(int $groupid, array $contextids): bool {
        $criteria = [
            'itemtype' => self::TYPE_GROUP,
            'itemid' => $groupid,
            'contextid' => $contextids,
        ];
        return self::count_by($criteria) > 0;
    }

    /**
     * Get the members of a group, bypassing any capability checks.
     *
     * @param int $groupid The group id to get members for.
     * @return array The list of user ids in the group.
     */
    private static function get_group_members(int $groupid): array {
        global $DB;
        return $DB->get_fieldset('groups_members', 'userid', ['groupid' => $groupid]);
    }

    /**
     * Get the exemption object from a raw record.
     *
     * @param \stdClass $record The database record to hydrate.
     * @return penalty_exemption The exemption record.
     */
    private static function from_record(\stdClass $record): penalty_exemption {
        $exemption = new penalty_exemption(
            $record->itemtype,
            $record->itemid,
            $record->contextid,
            $record->reason,
            $record->reasonformat
        );
        $exemption->id = $record->id;
        $exemption->timecreated = $record->timecreated ?? null;
        $exemption->timemodified = $record->timemodified ?? null;
        $exemption->usermodified = $record->usermodified ?? null;

        return $exemption;
    }

    /**
     * Get a list of exemption objects from a list of raw records.
     *
     * @param array $records The database records to hydrate.
     * @return array<penalty_exemption> The list of exemptions.
     */
    private static function from_records(array $records): array {
        return array_map([self::class, 'from_record'], $records);
    }

    /**
     * Get the SQL conditions and parameters for the given criteria.
     *
     * @param array $criteria The criteria to build the SQL conditions from.
     * @return array The SQL conditions and parameters.
     */
    private static function get_select_params(array $criteria): array {
        global $DB;

        $conditions = [];
        $params = [];

        foreach ($criteria as $field => $value) {
            [$insql, $inparams] = $DB->get_in_or_equal($value, SQL_PARAMS_NAMED);
            $conditions[] = "$field $insql";
            $params = array_merge($params, $inparams);
        }

        $conditions = implode(' AND ', $conditions);
        return [$conditions, $params];
    }

    /**
     * Trigger an event.
     *
     * @param string $eventclass Event class.
     */
    private function trigger_event(string $eventclass) {
        $eventclass::create([
            'objectid' => $this->get_id(),
            'contextid' => $this->get_contextid(),
            'other' => [
                'itemtype' => $this->get_itemtype(),
                'itemid' => $this->get_itemid(),
            ],
        ])->trigger();
    }
}
