<?php

class waOAuthController extends waViewController
{
    public function execute()
    {
        $provider = waRequest::get('provider');
        $app = waRequest::get('app');
        if ($app) {
            $this->getStorage()->set('auth_app', $app);
            $params = waRequest::get();
            unset($params['app']); unset($params['provoder']);
            if ($params) {
                $this->getStorage()->set('auth_params', $params);
            }
        }
        $config = wa()->getAuthConfig();
        if (isset($config['adapters'][$provider])) {
            $auth = wa()->getAuth($provider, $config['adapters'][$provider]);
            $data = $auth->auth();
            $this->afterAuth($data);
        } else {
            throw new waException('Unknown auth provider');
        }
    }

    /**
     * @param array $data
     * @return waContact
     */
    protected function afterAuth($data)
    {
        $app_id = $this->getStorage()->get('auth_app');
        $contact_id = 0;
        // find contact by auth adapter id, i.e. facebook_id
        $contact_data_model = new waContactDataModel();
        $row = $contact_data_model->getByField(array(
            'field' => $data['source'].'_id',
            'value' => $data['source_id'],
            'sort' => 0
        ));
        if ($row) {
            $contact_id = $row['contact_id'];
        }
        // try find user by email
        if (!$contact_id && isset($data['email'])) {
            $sql = "SELECT c.id FROM wa_contact_email e
            JOIN wa_contact c ON e.contact_id = c.id
            WHERE e.email = s:email AND e.sort = 0 AND c.password != ''";
            $contact_model = new waContactModel();
            $contact_id = $contact_model->query($sql, array('email' => $data['email']))->fetchField('id');
        }
        // create new contact
        if (!$contact_id) {
            $contact = new waContact();
            $data[$data['source'].'_id'] = $data['source_id'];
            $data['create_method'] = $data['source'];
            $data['create_app_id'] = $app_id;
            // set random password (length = default hash length - 1, to disable ability auth using login and password)
            $contact->setPassword(substr(waContact::getPasswordHash(uniqid(time(), true)), 0, -1), true);
            unset($data['source']);
            unset($data['source_id']);
            if (isset($data['photo_url'])) {
                $photo_url = $data['photo_url'];
                unset($data['photo_url']);
            } else {
                $photo_url = false;
            }
            $contact->save($data);
            $contact_id = $contact->getId();

            if ($contact_id && $photo_url) {
                $photo_url_parts = explode('/', $photo_url);
                // copy photo to tmp dir
                $path = wa()->getTempPath('auth_photo/'.$contact_id.'.'.end($photo_url_parts), $app_id);
                $photo = file_get_contents($photo_url);
                file_put_contents($path, $photo);
                $contact->setPhoto($path);
            }
        } else {
            $contact = new waContact($contact_id);
        }

        // auth user
        if ($contact_id) {
            wa()->getAuth()->auth(array('id' => $contact_id));
            return $contact;
        }
        return false;
    }
}