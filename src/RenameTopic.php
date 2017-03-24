<?php

/**
 * @package   Rename Topic
 * @version   1.0
 * @author    John Rayes <live627@gmail.com>
 * @copyright Copyright (c) 2017, John Rayes
 * @license   http://opensource.org/licenses/MIT MIT
 */
class RenameTopic
{
    /**
     * The database object
     * @var database
     */
    protected $db = null;

    protected $id_topic, $id_first_msg, $id_member_started;

    public function __construct()
    {
        global $topic, $user_info;

        require_once(SUBSDIR.'/Topic.subs.php');
        list ($this->id_topic, , , $this->id_first_msg, , $this->id_member_started) = array_values(getTopicInfo($topic));
        $this->db = database();
        $this->response_prefix = response_prefix();
    }

    /**
     * Register hooks to the system
     *
     * @return array
     */
    public static function registerAll()
    {
        $hook_functions = [
            ['integrate_load_permissions', self::class.'::load_permissions'],
            ['integrate_action_post_before', self::class.'::canPost'],
            ['integrate_before_modify_post', self::class.'::before_modify_post'],
        ];
        foreach ($hook_functions as list($hook, $function)) {
            add_integration_function($hook, $function, '', false);
        }
    }

    public static function load_permissions(
        &$permissionGroups,
        &$permissionList,
        &$leftPermissionGroups,
        &$hiddenPermissions,
        &$relabelPermissions
    ) {
        $permissionList['membergroup'] = elk_array_insert(
            $permissionList['membergroup'],
            'modify_replies',
            ['rename_topic' => [true, 'topic', 'moderate']]
        );
    }

    public static function canPost()
    {
        global $topic;

        // I'm lazy.
        if (!empty($topic) && isset($_REQUEST['msg']) && (new self)->check()) {
            add_integration_function('integrate_buffer', self::class.'::buffer', '', false);
        }
    }

    public function check()
    {
        global $user_info;

        $canRenameTopic = $_REQUEST['msg'] == $this->id_first_msg;
        if ($this->id_member_started == $user_info['id']) {
            $canRenameTopic &= allowedTo('rename_topic_own');
        } else {
            $canRenameTopic &= allowedTo('rename_topic_any');
        }

        return $canRenameTopic;
    }

    public static function buffer($buffer)
    {
        global $txt;

        loadLanguage('RenameTopic');

        return preg_replace(
            '/required=\\"required\\" \/>(.+)<label for=\\"icon\\">/s',
            'required="required" /><br><label class="smalltext"><input type="checkbox" name="renametopic" class="input_check" /> '.$txt['rename_topic'].'</label>$1<label for="icon">',
            $buffer
        );
    }

    public static function before_modify_post(
        &$messages_columns,
        &$update_parameters,
        &$msgOptions,
        &$topicOptions,
        &$posterOptions,
        &$messageInts
    ) {
        $rt = new self;
        if (!empty($_POST['renametopic']) && $rt->check() && isset($msgOptions['subject'])) {
            $rt->updateTopicSubject($msgOptions['subject']);
        }
    }

    /**
     * Update topic subject.
     *
     * @param string $custom_subject
     */
    public function updateTopicSubject($custom_subject)
    {
        $this->db->query(
            '',
            '
                UPDATE {db_prefix}messages
                SET subject = {string:subject}
                WHERE id_topic = {int:current_topic}
                    AND id_msg != {int:id_first_msg}',
            [
                'current_topic' => $this->id_topic,
                'id_first_msg' => $this->id_first_msg,
                'subject' => $this->response_prefix.$custom_subject,
            ]
        );
    }
}