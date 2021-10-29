<?php

defined('MOODLE_INTERNAL') || die();


require_once($CFG->dirroot . "/admin/renderer.php");
/**
 * renderer file description here.
 *
 * @package    theme_notclassic
 * @copyright  2021 SysBind Ltd. <service@sysbind.co.il>
 * @auther     schindlerl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class theme_notclassic_core_admin_renderer extends core_admin_renderer {


    /**
     * Displays all known plugins and links to manage them
     *
     * This default implementation renders all plugins into one big table.
     *
     * @param core_plugin_manager $pluginman provides information about the plugins.
     * @param array $options filtering options
     * @return string HTML code
     */
    public function plugins_control_panel(core_plugin_manager $pluginman, array $options = array()) {

        $plugininfo = $pluginman->get_plugins();

        // Filter the list of plugins according the options.
        if (!empty($options['updatesonly'])) {
            $updateable = array();
            foreach ($plugininfo as $plugintype => $pluginnames) {
                foreach ($pluginnames as $pluginname => $pluginfo) {
                    $pluginavailableupdates = $pluginfo->available_updates();
                    if (!empty($pluginavailableupdates)) {
                        foreach ($pluginavailableupdates as $pluginavailableupdate) {
                            $updateable[$plugintype][$pluginname] = $pluginfo;
                        }
                    }
                }
            }
            $plugininfo = $updateable;
        }

        if (!empty($options['contribonly'])) {
            $contribs = array();
            foreach ($plugininfo as $plugintype => $pluginnames) {
                foreach ($pluginnames as $pluginname => $pluginfo) {
                    if (!$pluginfo->is_standard()) {
                        $contribs[$plugintype][$pluginname] = $pluginfo;
                    }
                }
            }
            $plugininfo = $contribs;
        }

        if (empty($plugininfo)) {
            return '';
        }

        $table = new html_table();
        $table->id = 'plugins-control-panel';
        $table->head = array(
            get_string('displayname', 'core_plugin'),
            get_string('version', 'core_plugin'),
            get_string('availability', 'core_plugin'),
            get_string('actions', 'core_plugin'),
            get_string('notes','core_plugin'),
        );
        $table->headspan = array(1, 1, 1, 2, 1);
        $table->colclasses = array(
            'pluginname', 'version', 'availability', 'settings', 'uninstall', 'notes'
        );

        foreach ($plugininfo as $type => $plugins) {
            $heading = $pluginman->plugintype_name_plural($type);
            $pluginclass = core_plugin_manager::resolve_plugininfo_class($type);
            $pluginclass = core_plugin_manager::resolve_plugininfo_class($type);
            if ($manageurl = $pluginclass::get_manage_url()) {
                $heading .= $this->output->action_icon($manageurl, new pix_icon('i/settings',
                    get_string('settings', 'core_plugin')));
            }
            $header = new html_table_cell(html_writer::tag('span', $heading, array('id'=>'plugin_type_cell_'.$type)));
            $header->header = true;
            $header->colspan = array_sum($table->headspan);
            $header = new html_table_row(array($header));
            $header->attributes['class'] = 'plugintypeheader type-' . $type;
            $table->data[] = $header;

            if (empty($plugins)) {
                $msg = new html_table_cell(get_string('noneinstalled', 'core_plugin'));
                $msg->colspan = array_sum($table->headspan);
                $row = new html_table_row(array($msg));
                $row->attributes['class'] .= 'msg msg-noneinstalled';
                $table->data[] = $row;
                continue;
            }

            foreach ($plugins as $name => $plugin) {
                $row = new html_table_row();
                $row->attributes['class'] = 'type-' . $plugin->type . ' name-' . $plugin->type . '_' . $plugin->name;

                if ($this->page->theme->resolve_image_location('icon', $plugin->type . '_' . $plugin->name, null)) {
                    $icon = $this->output->pix_icon('icon', '', $plugin->type . '_' . $plugin->name, array('class' => 'icon pluginicon'));
                } else {
                    $icon = $this->output->spacer();
                }
                $status = $plugin->get_status();
                $row->attributes['class'] .= ' status-'.$status;
                $pluginname  = html_writer::tag('div', $icon.$plugin->displayname, array('class' => 'displayname')).
                    html_writer::tag('div', $plugin->component, array('class' => 'componentname'));
                $pluginname  = new html_table_cell($pluginname);

                $version = html_writer::div($plugin->versiondb, 'versionnumber');
                if ((string)$plugin->release !== '') {
                    $version = html_writer::div($plugin->release, 'release').$version;
                }
                $version = new html_table_cell($version);

                $isenabled = $plugin->is_enabled();
                if (is_null($isenabled)) {
                    $availability = new html_table_cell('');
                } else if ($isenabled) {
                    $row->attributes['class'] .= ' enabled';
                    $availability = new html_table_cell(get_string('pluginenabled', 'core_plugin'));
                } else {
                    $row->attributes['class'] .= ' disabled';
                    $availability = new html_table_cell(get_string('plugindisabled', 'core_plugin'));
                }

                $settingsurl = $plugin->get_settings_url();
                if (!is_null($settingsurl)) {
                    $settings = html_writer::link($settingsurl, get_string('settings', 'core_plugin'), array('class' => 'settings'));
                } else {
                    $settings = '';
                }
                $settings = new html_table_cell($settings);

                if ($uninstallurl = $pluginman->get_uninstall_url($plugin->component, 'overview')) {
                    $uninstall = html_writer::link($uninstallurl, get_string('uninstall', 'core_plugin'));
                } else {
                    $uninstall = '';
                }
                $uninstall = new html_table_cell($uninstall);

                if ($plugin->is_standard()) {
                    $row->attributes['class'] .= ' standard';
                    $source = '';
                } else {
                    $row->attributes['class'] .= ' extension';
                    $source = html_writer::div(get_string('sourceext', 'core_plugin'), 'source badge badge-info');
                }

                if ($status === core_plugin_manager::PLUGIN_STATUS_MISSING) {
                    $msg = html_writer::div(get_string('status_missing', 'core_plugin'), 'statusmsg badge badge-danger');
                } else if ($status === core_plugin_manager::PLUGIN_STATUS_NEW) {
                    $msg = html_writer::div(get_string('status_new', 'core_plugin'), 'statusmsg badge badge-success');
                } else {
                    $msg = '';
                }

                $requriedby = $pluginman->other_plugins_that_require($plugin->component);
                if ($requriedby) {
                    $requiredby = html_writer::tag('div', get_string('requiredby', 'core_plugin', implode(', ', $requriedby)),
                        array('class' => 'requiredby'));
                } else {
                    $requiredby = '';
                }

                $updateinfo = '';
                if (is_array($plugin->available_updates())) {
                    foreach ($plugin->available_updates() as $availableupdate) {
                        $updateinfo .= $this->plugin_available_update_info($pluginman, $availableupdate);
                    }
                }

                $notes = new html_table_cell($source.$msg.$requiredby.$updateinfo);
                $myNote = new html_table_cell("private");

                $row->cells = array(
                    $pluginname, $version, $availability, $settings, $uninstall, $notes ,$myNote
                );
                $table->data[] = $row;
            }
        }

        return html_writer::table($table);
    }
}