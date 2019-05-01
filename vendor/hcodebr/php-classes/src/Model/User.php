<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model
{
    const SESSION = "User";
    const SECRET = "chimitchanga123";

    public static function login($login, $password)
    {
        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :login", [
            ":login" => $login
        ]);

        if (count($results) === 0) {
            throw new \Exception("Usuário ou senha incorreto");
        }

        $data = $results[0];

        if (password_verify($password, $data["despassword"])) {

            $user = new User();

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;
        } else {
            throw new \Exception("Usuário ou senha incorreto");
        }
    }

    public static function verifyLogin($inadmin = true)
    {
        if (
            !isset($_SESSION[User::SESSION])
            ||
            !$_SESSION[User::SESSION]
            ||
            !(int)$_SESSION[User::SESSION]["iduser"] > 0
            ||
            (bool)$_SESSION[User::SESSION]["inadmin"] !== $inadmin
        ) {

            header("Location: /admin/login");

            exit;
        }
    }

    public static function logout()
    {

        $_SESSION[User::SESSION] = NULL;
    }

    public static function listAll()
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) ORDER BY b.desperson");

        return $results;
    }

    public function save()
    {

        $sql = new Sql();

        /* CALL `db_ecommerce`.`sp_users_save`(<{pdesperson VARCHAR(64)}>, <{pdeslogin VARCHAR(64)}>, <{pdespassword VARCHAR(256)}>, <{pdesemail VARCHAR(128)}>, <{pnrphone BIGINT}>, <{pinadmin TINYINT}>);*/

        $results = $sql->select(
            "CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)",
            [
                ":desperson" => $this->getdesperson(),
                ":deslogin" => $this->getdeslogin(),
                ":despassword" => $this->getdespassword(),
                ":desemail" => $this->getdesemail(),
                ":nrphone" => $this->getnrphone(),
                ":inadmin" => $this->getinadmin()
            ]
        );

        $this->setData($results[0]);
    }

    public function get($iduser)
    {

        $sql = new Sql();

        $results = $sql->select(
            "SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser=:iduser",
            [
                ":iduser" => $iduser
            ]
        );

        $this->setData($results[0]);
    }

    public function update()
    {

        $sql = new Sql();

        $results = $sql->select(
            "CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)",
            [
                ":iduser" => $this->getiduser(),
                ":desperson" => $this->getdesperson(),
                ":deslogin" => $this->getdeslogin(),
                ":despassword" => $this->getdespassword(),
                ":desemail" => $this->getdesemail(),
                ":nrphone" => $this->getnrphone(),
                ":inadmin" => $this->getinadmin()
            ]
        );

        $this->setData($results[0]);
    }

    public function delete()
    {

        $sql = new Sql();

        $sql->query(
            "CALL sp_users_delete(:iduser)",
            [
                ":iduser" => $this->getiduser()
            ]
        );
    }

    public static function getForgot($email, $inadmin = true)
    {

        $sql = new Sql();

        $results = $sql->select(
            "SELECT * FROM tb_persons a INNER JOIN tb_users b USING(idperson) WHERE a.desemail = :email",
            [
                ":email" => $email
            ]
        );

        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha");
        } else {
            $data = $results[0];

            $results2 = $sql->select(
                "CALL sp_userspasswordsrecoveries_create(:iduser, :desip)",
                [
                    ":iduser" => $data["iduser"],
                    ":desip" => $_SERVER["REMOTE_ADDR"]
                ]
            );

            if (count($results2) === 0) {
                throw new \Exception("Não foi possível recuperar a senha");
            } else {
                $dataRecovery = $results2[0];
                $iv = random_bytes(openssl_cipher_iv_length('aes-256-cbc'));
                $code = openssl_encrypt($dataRecovery['idrecovery'], 'aes-256-cbc', User::SECRET, 0, $iv);
                $result = base64_encode($iv . $code);
                if ($inadmin === true) {
                    $link = "http://www.ecommerce.com.br/admin/forgot/reset?code=$result";
                } else {
                    $link = "http://www.ecommerce.com.br/forgot/reset?code=$result";
                }
                $mailer = new Mailer($data['desemail'], $data['desperson'], "Redefinir senha da Hcode Store", "forgot", array(
                    "name" => $data['desperson'],
                    "link" => $link
                ));
                $mailer->send();

                return $link;
            }
        }
    }

    public static function validForgotDecrypt($result)
    {
        $result = base64_decode($result);
        $code = mb_substr($result, openssl_cipher_iv_length('aes-256-cbc'), null, '8bit');
        $iv = mb_substr($result, 0, openssl_cipher_iv_length('aes-256-cbc'), '8bit');
        $idrecovery = openssl_decrypt($code, 'aes-256-cbc', User::SECRET, 0, $iv);
        $sql = new Sql();
        $results = $sql->select("
         SELECT *
         FROM tb_userspasswordsrecoveries a
         INNER JOIN tb_users b USING(iduser)
         INNER JOIN tb_persons c USING(idperson)
         WHERE
         a.idrecovery = :idrecovery
         AND
         a.dtrecovery IS NULL
         AND
         DATE_ADD(a.dtregister, INTERVAL 1 HOUR) >= NOW();
         ", array(
            ":idrecovery" => $idrecovery
        ));
        var_dump($results[0]);
        if (count($results) === 0) {
            throw new \Exception("Não foi possível recuperar a senha.");
        } else {
            return $results[0];
        }
    }

    public static function setForgotUsed($idrecovery)
    {

        $sql = new Sql();

        $sql->query(
            "UPDATE tb_userspasswordsrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery",
            [
                ":idrecovery" => $idrecovery
            ]
        );
    }

    public function setPassword($password)
    {

        $sql = new Sql();

        $sql->query(
            "UPDATE tb_users SET despassword = :password WHERE iduser = :iduser",
            [
                ":password" => $password,
                ":iduser" => $this->getiduser()
            ]
        );
    }
}
