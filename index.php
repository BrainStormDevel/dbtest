<?php

$mysqli = new mysqli("127.0.0.1", "root", "123456", "citynewsdb");

class Bank
{
	protected $db;
	
	public function __construct(mysqli $db)
    {
        $this->db = $db;
    }
	
	public function addUser(string $fiscalcode, float $balance = 0.00, $timestamp = NULL) 
	{
		try {
			if (empty($fiscalcode)) {
				throw new Exception('Codice Fiscale vuoto', 0);
			}
			if (preg_match('/[^a-z0-9]/i', $fiscalcode)) {
				throw new Exception('Codice Fiscale non valido', 0);
			}
			$this->db->autocommit(true);
			$stmt = $this->db->prepare("INSERT INTO user (fiscal_code, balance, last_time_stamp) VALUES (?, ?, ?)");
			$stmt->bind_param('sds', $fiscalcode, $balance, $timestamp);
			$stmt->execute();
			return $stmt->insert_id;
		}
		catch (\Exception $e) {
			return 0;
		}
	}
	public function makeTransaction(string $fiscalcode1, string $fiscalcode2, float $balance = 0.00)
	{
		try {
			if (preg_match('/[^a-z0-9]/i', $fiscalcode1)) {
				throw new Exception('Codice Fiscale destinatario non valido');
			}
			if (preg_match('/[^a-z0-9]/i', $fiscalcode2)) {
				throw new Exception('Codice Fiscale mittente non valido');
			}
			if ($balance < 0) {
				throw new Exception('L\'importo non può essere negativo');
			}
			$qsql = "SELECT * FROM user WHERE fiscal_code=?";
			$stmt = $this->db->prepare($qsql);
			$stmt->bind_param("s", $fiscalcode2);
			$stmt->execute();
			$result = $stmt->get_result();
			$datamittente = $result->fetch_assoc();
			if (empty($datamittente)) {
				$mid = $this->addUser($fiscalcode2);
				return 'Il mittente non ha credito';
			}
			if ($datamittente['balance'] <= 0) {
				return 'Il mittente non ha credito';
			}
			$stmt = $this->db->prepare("SELECT * FROM user WHERE fiscal_code=?");
			$stmt->bind_param("s", $fiscalcode1);
			$stmt->execute();
			$result = $stmt->get_result();
			$datadestinatario = $result->fetch_assoc();
			if (empty($datadestinatario)) {
				$did = $this->addUser($fiscalcode1);
				$qsql = "SELECT * FROM user WHERE id=?";
				$stmt = $this->db->prepare($qsql);
				$stmt->bind_param("i", $did);
				$stmt->execute();
				$result = $stmt->get_result();
				$datadestinatario = $result->fetch_assoc();
			}
			$this->db->autocommit(false);
			$stmt = $this->db->prepare("UPDATE user SET balance = balance - ?, last_time_stamp = NOW() WHERE id = ?");
			$stmt->bind_param('di',$balance, $datamittente['id']);
			$stmt->execute();
			$stmt = $this->db->prepare("UPDATE user SET balance = balance + ?, last_time_stamp = NOW() WHERE id = ?");
			$stmt->bind_param('di',$balance, $datadestinatario['id']);
			$stmt->execute();
			$stmt = $this->db->prepare("INSERT INTO transfers (from_user_id, to_user_id, value) VALUES (?,?,?)");
			$stmt->bind_param('iid',$datamittente['id'],$datadestinatario['id'], $balance);
			$stmt->execute();
			$this->db->commit();
			$this->db->autocommit(true);
			return 'Operazione Effettuata Con Successo';
			
		}
		catch (\Exception $e) {
			$dbconnect->rollback();
			$this->db->autocommit(true);
			return $e->getMessage();
		}
	}
	public function listOperations(string $fiscalcode)
	{
		try {
			$stmt = $this->db->prepare("SELECT t.value FROM transfers t INNER JOIN user u ON t.from_user_id = u.id OR t.to_user_id = u.id WHERE u.fiscal_code=?");
			$stmt->bind_param("s", $fiscalcode);
			$stmt->execute();
			$result = $stmt->get_result();
			$data = $result->fetch_assoc();
			return json_encode($data);
		}
		catch (\Exception $e) {
			return $e->getMessage();
		}
	}
}

$mybank = new Bank($mysqli);
//$test = $mybank->makeTransaction('a', 'BNSDVD84D13H703G', 100);
$test = $mybank->listOperations('BNSDVD84D13H703G');
echo $test;