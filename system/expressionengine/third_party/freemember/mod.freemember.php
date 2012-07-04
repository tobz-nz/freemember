<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/*
 * FreeMember add-on for ExpressionEngine
 * Copyright (c) 2012 Adrian Macneil
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

include(PATH_THIRD.'freemember/config.php');

class Freemember
{
	private static $login_errors;
	private static $registration_errors;
	private static $update_profile_errors;
	private static $forgot_password_errors;
	private static $reset_password_errors;

	protected $tag_vars;

	public function __construct()
	{
		$this->EE =& get_instance();
		$this->EE->load->library('freemember_lib');
	}

	/**
	 * Login form tag
	 */
	public function login()
	{
		// form fields
		$this->tag_vars = array();
		$this->_add_field('email', 'email');
		$this->_add_field('username');
		$this->_add_field('password', 'password');
		$this->_add_field('auto_login', 'checkbox');

		// inline errors
		$this->_add_errors(self::$login_errors);

		return $this->_build_form('act_login');
	}

	/**
	 * Login form action
	 */
	public function act_login()
	{
		self::$login_errors = $this->EE->freemember->login();
		$this->_action_complete(self::$login_errors);
	}

	/**
	 * Register form tag
	 */
	public function register()
	{
		if ($error = $this->EE->freemember->can_register()) return $error;

		// form fields
		$this->tag_vars = array();
		$this->_add_member_fields();

		// generate captcha
		if ($this->EE->config->item('use_membership_captcha') == 'y')
		{
			$this->tag_vars[0]['captcha'] = $this->EE->functions->create_captcha();
		}

		// inline errors
		$this->_add_errors(self::$registration_errors);

		return $this->_build_form('act_register');
	}

	/**
	 * Register form action
	 */
	public function act_register()
	{
		self::$registration_errors = $this->EE->freemember->register();
		$this->_action_complete(self::$registration_errors);
	}

	/**
	 * Update form tag
	 */
	public function update_profile()
	{
		if ($error = $this->EE->freemember->can_update()) return $error;

		$member = $this->EE->freemember->current_member();

		// form fields
		$this->tag_vars = array();
		$this->_add_member_fields($member);

		// inline errors
		$this->_add_errors(self::$update_profile_errors);

		return $this->_build_form('act_update_profile');
	}

	/**
	 * Update form action
	 */
	public function act_update_profile()
	{
		self::$update_profile_errors = $this->EE->freemember->update_profile();
		$this->_action_complete(self::$update_profile_errors);
	}

	/**
	 * Display member public profiles
	 */
	public function members()
	{
		$search = $this->EE->TMPL->tagparams;
		$members = $this->EE->freemember_model->find_members($search);
		if ($members)
		{
			return $this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $members);
		}

		return $this->EE->TMPL->no_results();
	}

	/**
	 * Forgot Password tag
	 */
	public function forgot_password()
	{
		$this->tag_vars = array();
		$this->_add_field('email', 'email');

		// inline errors
		$this->_add_errors(self::$forgot_password_errors);

		return $this->_build_form('act_forgot_password');
	}

	/**
	 * Forgot password action
	 */
	public function act_forgot_password()
	{
		self::$forgot_password_errors = $this->EE->freemember->forgot_password();
		$this->_action_complete(self::$forgot_password_errors);
	}

	public function reset_password()
	{
		// was reset code specified in params?
		if (($reset_code = $this->EE->TMPL->fetch_param('reset_code')) === false)
		{
			// freemember 1.x compabitility
			if (($reset_code = $this->EE->TMPL->fetch_param('code')) === false)
			{
				// reset code defaults to last segment
				$reset_code = $this->EE->uri->segment($this->EE->uri->total_segments());
			}
		}

		// verify reset code
		$member = $this->EE->freemember_model->find_member_by_reset_code($reset_code);
		if (empty($member))
		{
			return $this->EE->TMPL->no_results();
		}

		$this->tag_vars = array();
		$this->_add_field('password', 'password');
		$this->_add_field('password_confirm', 'password');

		// not fields, but available in the template
		$this->tag_vars[0]['email'] = $member->email;
		$this->tag_vars[0]['username'] = $member->username;
		$this->tag_vars[0]['screen_name'] = $member->screen_name;

		// inline errors
		$this->_add_errors(self::$reset_password_errors);

		return $this->_build_form('act_reset_password', array('reset_code' => $reset_code));
	}

	public function act_reset_password()
	{
		self::$reset_password_errors = $this->EE->freemember->reset_password();
		$this->_action_complete(self::$reset_password_errors);
	}

	/**
	 * Placing this tag on a page logs the user out immediately
	 */
	public function logout()
	{
		$_GET['return_url'] = $this->EE->TMPL->fetch_param('return');
		$this->act_logout();
	}

	public function logout_url()
	{
		$params = array_filter(array('return_url' => $this->EE->TMPL->fetch_param('return')));

		$url = $this->EE->functions->fetch_site_index().QUERY_MARKER.
			'ACT='.$this->EE->functions->fetch_action_id(__CLASS__, 'act_logout');

		if ( ! empty($params))
		{
			$url .= '&'.http_build_query($params);
		}

		return $url;
	}

	public function act_logout()
	{
		$this->EE->freemember->logout();
		$this->_action_complete();
	}

	/**
	 * Add a field helper to tag_vars
	 */
	protected function _add_field($name, $type = 'text', $force_value = null)
	{
		if (null !== $force_value || 'password' == $type)
		{
			$value = $force_value;
		}
		elseif (false === ($value = $this->EE->input->post($name)))
		{
			// nothing posted, did we already have a template variable set?
			$value = isset($this->tag_vars[0][$name]) ? $this->tag_vars[0][$name] : false;
		}

		// assume email field type
		if ('text' == $type && ('email' == $name || 'email_confirm' == $name))
		{
			$type = 'email';
		}

		$this->tag_vars[0][$name] = $value;
		$this->tag_vars[0]['error:'.$name] = false;

		$field = "<input type='$type' name='$name' id='$name'";
		if ($type == 'checkbox')
		{
			$checked = $value ? ' checked ' : '';
			$field = "<input type='hidden' name='$name' value='' />$field value='1' $checked";
			$this->tag_vars[0][$name.'_checked'] = $checked;
		}
		else
		{
			$field .= " value='$value'";
		}

		$this->tag_vars[0]["field:$name"] = $field." />";
	}

	protected function _add_member_fields($member = null)
	{
		// standard member fields
		foreach ($this->EE->freemember_model->member_fields() as $field)
		{
			if ($member)
			{
				$this->tag_vars[0][$field] = $member->$field;
			}

			$this->_add_field($field);
		}

		// custom member fields
		foreach ($this->EE->freemember_model->member_custom_fields() as $field)
		{
			if ($member)
			{
				$field_id = 'm_field_id_'.$field->m_field_id;
				$this->tag_vars[0][$field_id] = $member->$field_id;
				$this->tag_vars[0][$field->m_field_name] = $member->$field_id;
			}

			$this->_add_field($field->m_field_name);
		}

		// these fields aren't directly mapped to the db
		$this->_add_field('email_confirm');
		$this->_add_field('current_password', 'password');
		$this->_add_field('password', 'password');
		$this->_add_field('password_confirm', 'password');
		$this->_add_field('captcha', 'text', false);
		$this->_add_field('accept_terms', 'checkbox');
	}

	/**
	 * Add inline errors to the tag_vars
	 */
	protected function _add_errors($errors)
	{
		if (is_array($errors))
		{
			foreach ($errors as $key => $value)
			{
				$this->tag_vars[0]["error:$key"] = $this->EE->freemember->wrap_error($value);
			}
		}
	}

	/**
	 * Output a form based on the current params and tag vars
	 */
	protected function _build_form($action, $extra_hidden = array())
	{
		$this->EE->load->add_package_path(PATH_MOD.'safecracker');
		$this->EE->load->library('safecracker_lib');

		$data = array();
		$data['action'] = $this->EE->functions->create_url($this->EE->uri->uri_string);
		$data['id'] = $this->EE->TMPL->fetch_param('form_id');
		$data['name'] = $this->EE->TMPL->fetch_param('form_name');
		$data['class'] = $this->EE->TMPL->fetch_param('form_class');

		$data['hidden_fields'] = $extra_hidden;
		$data['hidden_fields']['ACT'] = $this->EE->functions->fetch_action_id(__CLASS__, $action);
		$data['hidden_fields']['_params'] = $this->EE->safecracker->encrypt_input(serialize($this->EE->TMPL->tagparams));
		$data['hidden_fields']['return_url'] = $this->EE->TMPL->fetch_param('return');

		if ('PREVIOUS_URL' == $data['hidden_fields']['return_url'])
		{
			$this->EE->load->helper('url');
			$data['hidden_fields']['return_url'] = uri_string();
		}

		return $this->EE->functions->form_declaration($data).
			$this->EE->TMPL->parse_variables($this->EE->TMPL->tagdata, $this->tag_vars).'</form>';
	}

	/**
	 * After form submission, either display the errors or redirect to the return url
	 */
	protected function _action_complete($errors = null)
	{
		if (empty($errors))
		{
			if (($return_url = $this->EE->input->get_post('return_url')) != '')
			{
				$return_url = $this->EE->functions->create_url($return_url);
			}
			else
			{
				// pretty unlikely anyone will end up here
				$return_url = $this->EE->functions->fetch_site_index();
			}

			$this->EE->functions->redirect($return_url);
		}
		elseif ($this->EE->freemember->form_param('error_handling') == 'inline')
		{
			return $this->EE->core->generate_page();
		}

		return $this->EE->output->show_user_error(false, $errors);
	}
}

/* End of file */