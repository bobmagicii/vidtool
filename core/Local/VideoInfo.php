<?php

namespace Local;

use Nether\Common;

class VideoInfo {

	const
	StatusOK           = 0,
	StatusWrongCodec   = 1 << 0,
	StatusWrongEncoder = 1 << 1;

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected string
	$File;

	protected string
	$Codec = 'unknown';

	protected string
	$Encoder = 'unknown';

	protected int
	$Status = self::StatusOK;

	protected int
	$Error = self::StatusOK;

	protected string
	$FFProbe = '';

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	GetFile():
	string {

		return $this->File;
	}

	public function
	GetFileBasename():
	string {

		return basename($this->File);
	}

	public function
	GetFileDirname():
	string {

		return dirname($this->File);
	}

	public function
	GetEncoder():
	string {

		return $this->Encoder;
	}

	public function
	GetCodec():
	string {

		return $this->Codec;
	}

	public function
	GetFFProbe():
	string {

		return $this->FFProbe;
	}

	public function
	GetStatus():
	int {

		return $this->Status;
	}

	public function
	IsCodecGood():
	bool {

//		var_dump((($this->Status & static::StatusWrongCodec) === 0));

		return (($this->Status & static::StatusWrongCodec) === 0);
	}

	public function
	IsEncoderGood():
	bool {

		return (($this->Status & static::StatusWrongEncoder) === 0);
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	SetError(int $ErrNum):
	static {

		$this->Error = $ErrNum;

		return $this;
	}

	public function
	SetFile(string $Path):
	static {

		$this->File = $Path;

		return $this;
	}

	public function
	SetFFProbe(string $Data):
	static {

		$this->FFProbe = $Data;

		return $this;
	}

	public function
	SetCodec(string $Codec):
	static {

		$this->Codec = strtoupper($Codec);

		return $this;
	}

	public function
	SetEncoder(string $Encoder):
	static {

		$this->Encoder = $Encoder;

		return $this;
	}

	public function
	SetStatus(int $Status):
	static {

		if(($this->Status & $Status) === 0)
		$this->Status = $Status;

		return $this;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	DigestFFProbe(string $Data):
	static {

		$this->FFProbe = $Data;

		////////

		$Lines = Common\Datastore::FromString($this->FFProbe, PHP_EOL);
		$this->DigestFFProbe_VideoStreamCodec($Lines);
		$this->DigestFFProbe_Encoder($Lines);

		return $this;
	}

	protected function
	DigestFFProbe_VideoStreamCodec(Common\Datastore $Lines):
	void {

		$Videos = $Lines->Distill(fn(string $L)=> str_contains($L, 'Video: '));
		$Found = NULL;

		////////

		if(preg_match('/Video: ([^\s]+)/', $Videos->Current(), $Found))
		$this->SetCodec($Found[1]);

		return;
	}

	protected function
	DigestFFProbe_Encoder(Common\Datastore $Lines):
	void {

		$Lines = $Lines->Distill(fn(string $L)=> str_contains($L, 'encoder'));
		$Found = NULL;

		////////

		if(preg_match('/encoder[\h]*: (.+?)$/', $Lines->Current(), $Found))
		$this->SetEncoder($Found[1]);

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	static public function
	FromFile(string $Path):
	static {

		$Output = new static;
		$Output->SetFile($Path);

		return $Output;
	}

};

