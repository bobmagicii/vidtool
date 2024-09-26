<?php

namespace Local;

use Nether\Common;
use Nether\Console;

#[Console\Meta\Application('vidtool', '0.0.1', Phar: 'vidtool.phar')]
class App
extends Console\Client {

	#[Console\Meta\Command('check')]
	#[Console\Meta\Arg('file/folder')]
	#[Console\Meta\Toggle('ffprobe', 'Show the FFProbe data.')]
	#[Console\Meta\Toggle('move', 'Move the files that fail into a Todo folder.')]
	#[Console\Meta\Error(1, 'not found: %s')]
	public function
	HandleCheckVideoFiles():
	int {

		$What = realpath($this->GetInput(1) ?? '.');
		$OptMoveMode = ((int)$this->GetOption('move')) ?: 0;
		$OptShowProbe = $this->GetOption('ffprobe') ?? FALSE;

		$Codecs = new Common\Datastore([ 'HEVC' ]);
		$Encoders = new COmmon\Datastore([ 'HandBrake' ]);
		$CheckExts = new Common\Datastore([ 'mp4' ]);

		$Files = NULL;
		$File = NULL;
		$Cmd = NULL;
		$Row = NULL;
		$Report = new Common\Datastore;

		////////

		if(!file_exists($What))
		$this->Quit(1, $What);

		////////

		// handle file or directory scan.

		if(is_dir($What))
		$Files = Common\Filesystem\Indexer::DatastoreFromPath(realpath($What));
		else
		$Files = Common\Datastore::FromArray([ realpath($What) ]);

		// filter out files by ext type.

		$Files->Filter(function(string $F) use($CheckExts) {
			$Found = $CheckExts->Distill(
				fn(string $Ext)
				=> str_ends_with(strtolower($F), strtolower($Ext))
			);

			return $Found->Count() > 0;
		});

		$Files->Sort();

		// fetch file info.

		$this->PrintFilesStart($Files);
		$this->RunFilesProbe($Files, $Report);
		$this->PrintFilesReport($Report, $Codecs, $Encoders, $OptShowProbe);

		if($OptMoveMode > 0)
		$this->MoveFilesTodo($Report, $OptMoveMode);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	RunFilesProbe(Common\Datastore $Files, Common\Datastore $Report):
	void {

		$File = NULL;

		////////

		foreach($Files as $File) {
			$Cmd = Console\Struct\CommandLineUtil::Exec(sprintf(
				'ffprobe %s 2>&1',
				escapeshellarg($File)
			));

			$this->PrintLn(sprintf(
				$this->Format('>> %s...', $this->Theme::Muted),
				basename($File)
			));

			$Row = VideoInfo::FromFile($File);
			$Row->SetError($Cmd->Error);
			$Row->DigestFFProbe($Cmd->GetOutputString());

			$Report->Shove($Row->GetFile(), $Row);
		}

		$this->PrintLn('');

	}

	protected function
	PrintFilesStart(Common\Datastore $Files):
	void {

		$this->PrintStatus(sprintf(
			'Checking codecs on %d %s',
			$Files->Count(),
			Common\Values::IfOneElse($Files->Count(), 'file', 'files')
		));

		return;
	}

	protected function
	PrintFilesReport(Common\Datastore $Report, Common\Datastore $Codecs, Common\Datastore $Encoders, bool $FFProbe):
	void {

		$Report->Each(function(VideoInfo $Row) use($Codecs, $Encoders, $FFProbe) {

			$KeepCodec = $Codecs->Distill(
				fn(string $E)
				=> $E === $Row->GetCodec()
			);

			$KeepEnc = $Encoders->Distill(
				fn(string $E)
				=> str_starts_with($Row->GetEncoder(), $E)
			);

			////////

			if($Codecs->Count())
			if($KeepCodec->Count() === 0)
			$Row->PushStatus($Row::StatusWrongCodec);

			if($Encoders->Count())
			if($KeepEnc->Count() === 0)
			$Row->PushStatus($Row::StatusWrongEncoder);

			////////

			$MsgFile = $Row->GetFileBasename();

			$MsgCodec = match(TRUE) {
				(!$Row->IsCodecGood())
				=> $this->Format($Row->GetCodec(), $this->Theme::Error),

				(!$Row->IsEncoderGood())
				=> $this->Format($Row->GetCodec(), $this->Theme::Warning),

				default
				=> $this->Format($Row->GetCodec(), $this->Theme::OK)
			};

			$MsgEncoder = match(TRUE) {
				(!$Row->IsEncoderGood())
				=> $this->Format($Row->GetEncoder(), $this->Theme::Warning),

				default
				=> $this->Format($Row->GetEncoder(), $this->Theme::Muted)
			};

			$MsgFFProbe = $this->Format($Row->GetFFProbe(), $this->Theme::Muted);

			////////

			$this->PrintLn(sprintf(
				'[%s] %s (%s)',
				$MsgCodec, $MsgFile, $MsgEncoder
			));

			////////

			if($FFProbe)
			$this->PrintLn($MsgFFProbe, 2);

			return;
		});

		return;
	}

	protected function
	MoveFilesTodo(Common\Datastore $Report, int $MoveMode=0):
	void {

		$Report->Each(function(VideoInfo $Row) use($MoveMode) {

			if($Row->GetStatus() === $Row::StatusOK)
			return;

			if($MoveMode === 0)
			return;

			////////

			$Folder = 'Todo';

			if($MoveMode === 2)
			$Folder = $Row->GetCodec();

			if($MoveMode === 3)
			$Folder = $Row->GetEncoder();

			////////

			$File = Common\Filesystem\Util::Pathify(
				$Row->GetFileDirname(),
				$Folder,
				$Row->GetFileBasename()
			);

			$Dir = dirname($File);
			Common\Filesystem\Util::MkDir($Dir);

			rename($Row->GetFile(), $File);
			$Row->SetFile($File);

			return;
		});

		return;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	GetPharFiles():
	Common\Datastore {

		$Index = parent::GetPharFiles();
		$Index->Push('core');

		return $Index;
	}

	protected function
	GetPharFileFilters():
	Common\Datastore {

		$Filters = parent::GetPharFileFilters();

		$Filters->Push(function(string $File) {

			$DS = DIRECTORY_SEPARATOR;

			// dev deps that dont need to be.

			if(str_contains($File, "squizlabs{$DS}"))
			return FALSE;

			if(str_contains($File, "dealerdirect{$DS}"))
			return FALSE;

			if(str_contains($File, "netherphp{$DS}standards"))
			return FALSE;

			// unused deps from Nether\Common that dont need to be.

			if(str_contains($File, "monolog{$DS}"))
			return FALSE;

			////////

			return TRUE;
		});

		return $Filters;
	}

};

