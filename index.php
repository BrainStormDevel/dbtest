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
			/*if (empty($fiscalcode)) {
				throw new Exception('Codice Fiscale vuoto', 0);
			}
			if (preg_match('/[^a-z0-9]/i', $fiscalcode)) {
				throw new Exception('Codice Fiscale non valido', 0);
			}*/
			$timestamp = (($timestamp != NULL) ? $this->db->real_escape_string($timestamp) : 'NULL');
			$this->db->autocommit(true);
			$query = sprintf("INSERT INTO user (fiscal_code, balance, last_time_stamp) VALUES ('%s', '%f', $timestamp)", $this->db->real_escape_string($fiscalcode), $balance);
			$this->db->query($query);
			return $this->db->insert_id;
			/*$stmt = $this->db->prepare("INSERT INTO user (fiscal_code, balance, last_time_stamp) VALUES (?, ?, ?)");
			$stmt->bind_param('sds', $fiscalcode, $balance, $timestamp);
			$stmt->execute();
			return $stmt->insert_id;*/
		}
		catch (\Exception $e) {
			return 0;
		}
	}
	public function addFund(string $fiscalcode, float $balance = 0.00) 
	{
		try {
			$this->db->autocommit(true);
			$query = sprintf("INSERT IGNORE INTO user (fiscal_code, balance, last_time_stamp) VALUES ('%s', '%f', NOW()) ON DUPLICATE KEY UPDATE balance = balance + '%f', last_time_stamp = NOW()", $this->db->real_escape_string($fiscalcode), $balance, $balance);
			$this->db->query($query);
			return 'ok';
		}
		catch (\Exception $e) {
			return 0;
		}
	}	
	public function makeTransaction(string $fiscalcode1, string $fiscalcode2, float $balance = 0.00)
	{
		try {
			/*if (preg_match('/[^a-z0-9]/i', $fiscalcode1)) {
				throw new Exception('Codice Fiscale destinatario non valido');
			}
			if (preg_match('/[^a-z0-9]/i', $fiscalcode2)) {
				throw new Exception('Codice Fiscale mittente non valido');
			}*/
			if ($balance < 0) {
				throw new Exception('L\'importo non può essere negativo');
			}
			$this->db->autocommit(true);
			$query = sprintf("SELECT * FROM user WHERE fiscal_code='%s'", $this->db->real_escape_string($fiscalcode2));
			$result = $this->db->query($query);
			$datamittente = $result->fetch_all(MYSQLI_ASSOC);
			if (empty($datamittente)) {
				$mid = (int) $this->addUser($fiscalcode2);
				if ($balance > 0) {
					return 'Il mittente non ha credito';
				}
			}
			else {
				$mid = (int) $datamittente[0]['id'];
			}
			if ((($datamittente[0]['balance'] <= 0) || ($datamittente[0]['balance'] < $balance)) && ($balance > 0)) {
				return 'Il mittente non ha credito';
			}
			if ($mid <=0) {
				throw new Exception('Errore Mittente');
			}
			$query = sprintf("SELECT * FROM user WHERE fiscal_code='%s'", $this->db->real_escape_string($fiscalcode1));
			$result = $this->db->query($query);
			$datadestinatario = $result->fetch_all(MYSQLI_ASSOC);
			if (empty($datadestinatario)) {
				$did = (int) $this->addUser($fiscalcode1);
				$result = $this->db->query("SELECT * FROM user WHERE id='$did'");
				$datadestinatario = $result->fetch_all(MYSQLI_ASSOC);
			}
			else {
				$did = (int) $datadestinatario[0]['id'];
			}
			if ($mid <=0) {
				throw new Exception('Errore Destinatario');
			}
			$this->db->begin_transaction();
			$this->db->autocommit(false);
			$q1 = $this->db->query("SELECT balance FROM user WHERE id='$mid' FOR UPDATE");
			$qu1 = sprintf("UPDATE user SET balance = balance - '%f', last_time_stamp = NOW() WHERE id = '%d'", $balance, $mid);
			$this->db->query($qu1);
			$q2 = $this->db->query("SELECT balance FROM user WHERE id='$did' FOR UPDATE");
			$qu2 = sprintf("UPDATE user SET balance = balance + '%f', last_time_stamp = NOW() WHERE id = '%d'", $balance, $did);
			$this->db->query($qu2);
			$qui2 = sprintf("INSERT INTO transfers (from_user_id, to_user_id, value) VALUES ('%d','%d','%f')", $mid, $did, $balance);
			$this->db->query($qui2);
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
			$this->db->autocommit(true);
			$query = sprintf("SELECT IF((t.to_user_id = u.id), CONCAT('+', CAST(t.value AS CHAR CHARACTER SET utf8mb4)), CONCAT('-', CAST(t.value AS CHAR CHARACTER SET utf8mb4))) AS value, t.time_stamp FROM transfers t INNER JOIN user u ON t.from_user_id = u.id OR t.to_user_id = u.id WHERE u.fiscal_code= '%s'", $this->db->real_escape_string($fiscalcode));
			$result = $this->db->query($query);
			$data = $result->fetch_all(MYSQLI_ASSOC);
			return json_encode($data);
		}
		catch (\Exception $e) {
			return $e->getMessage();
		}
	}
}

$mybank = new Bank($mysqli);
echo "Aggiunta 1500 fondi a Mittente:";
echo $mybank->addFund('pippo""', 1500,00);
echo "<br><br>";
echo "Effettuo transazione 1000 con destinatario test inesistente e mittente pippo";
echo $mybank->makeTransaction('test', 'pippo""', 1000,00);
echo "<br><br>";
echo "Effettuo transazione 0 con destinatario test e mittente pippo";
echo "<br><br>";
echo $mybank->makeTransaction('test', 'pippo""', 0,00);
echo "Effettuo transazione 600 da mittente test esistente a pippo";
echo $mybank->makeTransaction('pippo""', 'test', 600,00);
echo "<br><br>";
echo "Stampo movimentazione di pippo\"\"";
echo $mybank->listOperations('pippo""');