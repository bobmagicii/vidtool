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
	$Filesize = 0;

	protected int
	$Duration = 0;

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
	GetFilesize():
	int {

		return $this->Filesize;
	}

	public function
	GetFilesizeClean():
	string {

		return Common\Units\Bytes::FromInt($this->Filesize)->Get();
	}

	public function
	GetEncoder():
	string {

		return $this->Encoder;
	}

	public function
	GetEncoderClean():
	string {

		$Words = Common\Datastore::FromString($this->Encoder, ' ');
		$Name = '';

		// if there was only one word then return it.

		if($Words->Count() === 1)
		return $this->Encoder;

		// otherwise drop words that looked like version crap.

		foreach($Words as $Word) {
			if(preg_match('/[0-9\.]/', $Word))
			continue;

			$Name .= "{$Word} ";
		}

		return trim($Name);
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
	GetSizeRate():
	float {

		if(!$this->Duration)
		return $this->Filesize;

		$Rate = $this->Filesize / $this->Duration;

		$MBit = $Rate / pow(Common\Values::BitsPerUnit, 2);
		$MBit = round($MBit, 3);

		return $MBit;
	}

	////////////////////////////////

	public function
	IsCodec(string $Codec):
	bool {

		return (strtolower($this->GetCodec()) === strtolower($Codec));
	}

	public function
	IsCodecLike(string $Codec):
	bool {

		return str_starts_with(
			strtolower($this->GetCodec()),
			strtolower($Codec)
		);
	}

	public function
	IsCodecGood():
	bool {

		return (($this->Status & static::StatusWrongCodec) === 0);
	}

	////////////////////////////////

	public function
	IsEncoder(string $Encoder):
	bool {

		return (strtolower($this->GetEncoder()) === strtolower($Encoder));
	}

	public function
	IsEncoderLike(string $Encoder):
	bool {

		return str_starts_with(
			strtolower($this->GetEncoder()),
			strtolower($Encoder)
		);
	}

	public function
	IsEncoderGood():
	bool {

		return (($this->Status & static::StatusWrongEncoder) === 0);
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	public function
	SetFile(string $Path):
	static {

		$this->File = $Path;

		$this->Filesize = filesize($this->File);

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
	SetDuration(int|string $Dur):
	static {

		if(is_string($Dur)) {
			$Dur = strtotime("1970-01-01 {$Dur}");
		}

		$this->Duration = $Dur;

		return $this;
	}

	////////////////////////////////

	public function
	SetError(int $ErrNum):
	static {

		$this->Error = $ErrNum;

		return $this;
	}

	public function
	SetStatus(int $Status):
	static {

		$this->Status = $Status;

		return $this;
	}

	public function
	PushStatus(int $Status):
	static {

		$Has = $this->Status & $Status;
		$Rem = $Status & ~$Has;

		////////

		if($Rem !== 0)
		$this->Status |= $Rem;

		////////

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
		$this->DigestFFProbe_VideoEncoder($Lines);
		$this->DigestFFProbe_VideoDuration($Lines);

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
	DigestFFProbe_VideoDuration(Common\Datastore $Lines):
	void {

		$Durrs = $Lines->Distill(fn(string $L)=> str_contains($L, 'Duration'));
		$Found = NULL;

		////////

		if(preg_match('/Duration: (.+?),/', $Durrs->Current(), $Found))
		$this->SetDuration($Found[1]);

		return;
	}

	protected function
	DigestFFProbe_VideoEncoder(Common\Datastore $Lines):
	void {

		$Encoders = $Lines->Distill(fn(string $L)=> str_contains($L, 'encoder'));
		$Found = NULL;

		////////

		if(preg_match('/encoder[\h]*: (.+?)$/', $Encoders->Current(), $Found))
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

