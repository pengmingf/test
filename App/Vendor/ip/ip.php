<?php
namespace App\Vendor\ip;

class ip {
	var $fh; //IP���ݿ��ļ����
	var $first; //��һ������
	var $last; //���һ������
	var $total; //��������

	//���캯��
	function __construct() {
		$this->fh = fopen(__DIR__ . '/qqwry.dat', 'rb'); //qqwry.dat�ļ�
		$this->first = $this->getLong4();
		$this->last = $this->getLong4();
		$this->total = ($this->last - $this->first) / 7; //ÿ������7�ֽ�
	}

	//���IP�Ϸ���
	function checkIp($ip) {
		$arr = explode('.',$ip);
		if(count($arr) !=4 ) {
			return false;
		} else {
			for ($i=0; $i < 4; $i++) {
				if ($arr[$i] <'0' || $arr[$i] > '255') {
					return false;
				}
			}
		}
		return true;
	}

	function getLong4() {
		//��ȡlittle-endian�����4���ֽ�ת��Ϊ��������
		$result = unpack('Vlong', fread($this->fh, 4));
		return $result['long'];
	}

	function getLong3() {
		//��ȡlittle-endian�����3���ֽ�ת��Ϊ��������
		$result = unpack('Vlong', fread($this->fh, 3).chr(0));
		return $result['long'];
	}

	//��ѯ��Ϣ
	function getInfo($data = "") {
		$char = fread($this->fh, 1);
		while (ord($char) != 0) { //���ҵ�����Ϣ��0����
			$data .= $char;
			$char = fread($this->fh, 1);
		}
		return $data;
	}

	//��ѯ������Ϣ
	function getArea() {
		$byte = fread($this->fh, 1); //��־�ֽ�
		switch (ord($byte)) {
			case 0: $area = ''; break; //û�е�����Ϣ
			case 1: //�������ض���
				fseek($this->fh, $this->getLong3());
				$area = $this->getInfo(); break;
			case 2: //�������ض���
			fseek($this->fh, $this->getLong3());
			$area = $this->getInfo(); break;
			default: $area = $this->getInfo($byte);  break; //����û�б��ض���
		}
		return $area;
	}

	function ip2addr($ip) {
		if(!$this -> checkIp($ip)){
			return false;
		}

		$ip = pack('N', intval(ip2long($ip)));

		//���ֲ���
		$l = 0;
		$r = $this->total;

		while($l <= $r) {
			$m = floor(($l + $r) / 2); //�����м�����
			fseek($this->fh, $this->first + $m * 7);
			$beginip = strrev(fread($this->fh, 4)); //�м������Ŀ�ʼIP��ַ
			fseek($this->fh, $this->getLong3());
			$endip = strrev(fread($this->fh, 4)); //�м������Ľ���IP��ַ

			if ($ip < $beginip) { //�û���IPС���м������Ŀ�ʼIP��ַʱ
				$r = $m - 1;
			} else {
				if ($ip > $endip) { //�û���IP�����м������Ľ���IP��ַʱ
					$l = $m + 1;
				} else { //�û�IP���м�������IP��Χ��ʱ
					$findip = $this->first + $m * 7;
					break;
				}
			}
		}

		//��ѯ���ҵ�����Ϣ
		fseek($this->fh, $findip);
		$location['beginip'] = long2ip($this->getLong4()); //�û�IP���ڷ�Χ�Ŀ�ʼ��ַ
		$offset = $this->getlong3();
		fseek($this->fh, $offset);
		$location['endip'] = long2ip($this->getLong4()); //�û�IP���ڷ�Χ�Ľ�����ַ
		$byte = fread($this->fh, 1); //��־�ֽ�
		switch (ord($byte)) {
			case 1:  //���Һ�������Ϣ�����ض���
				$countryOffset = $this->getLong3(); //�ض����ַ
				fseek($this->fh, $countryOffset);
				$byte = fread($this->fh, 1); //��־�ֽ�
				switch (ord($byte)) {
					case 2: //������Ϣ�������ض���
						fseek($this->fh, $this->getLong3());
						$location['country'] = $this->getInfo();
						fseek($this->fh, $countryOffset + 4);
						$location['area'] = $this->getArea();
						break;
					default: //������Ϣû�б������ض���
						$location['country'] = $this->getInfo($byte);
						$location['area'] = $this->getArea();
						break;
				}
				break;

			case 2: //������Ϣ���ض���
				fseek($this->fh, $this->getLong3());
				$location['country'] = $this->getInfo();
				fseek($this->fh, $offset + 8);
				$location['area'] = $this->getArea();
				break;

			default: //������Ϣû�б��ض���
				$location['country'] = $this->getInfo($byte);
				$location['area'] = $this->getArea();
				break;
		}

		//gb2312 to utf-8��ȥ������Ϣʱ��ʾ��CZ88.NET��
		foreach ($location as $k => $v) {
			$location[$k] = str_replace('CZ88.NET','',iconv('gb2312', 'utf-8', $v));
		}

		return $location;
	}

	//��������
	function __destruct() {
		fclose($this->fh);
	}
}
?>