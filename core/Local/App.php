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

		$What = $this->GetInput(1) ?? '.';
		$OptMove = ((int)$this->GetOption('move')) ?: 0;
		$OptFFProbe = $this->GetOption('ffprobe') ?? FALSE;

		// @todo 2024-09-26 pull --codecs else config file

		$Codecs = new Common\Datastore([ 'hevc' ]);
		$Encoders = new Common\Datastore([ 'handbrake' ]);
		$CheckExts = new Common\Datastore([ 'mp4' ]);

		$Path = NULL;
		$Report = NULL;
		$Files = NULL;

		////////

		$Path = realpath($What);

		if(!$Path)
		$this->Quit(1, $What);

		////////

		$Report = new Common\Datastore;
		$Files = $this->FetchFileList($Path, $CheckExts);

		$this->PrintFilesReportBegin($Files);
		$this->RunFilesProbe($Files, $Report);
		$this->PrintFilesReportEnd($Report, $Codecs, $Encoders, $OptFFProbe);

		if($OptMove > 0)
		$this->MoveFilesTodo($Report, $OptMove);

		return 0;
	}

	////////////////////////////////////////////////////////////////
	////////////////////////////////////////////////////////////////

	protected function
	FetchFileList(string $What, Common\Datastore $Exts):
	Common\Datastore {

		$Files = NULL;

		////////

		// given the path to a directory index all the files within it.
		// otherwise make an index with just this file.

		$Files = match(TRUE) {
			(is_dir($What))
			=> Common\Filesystem\Indexer::DatastoreFromPath(realpath($What)),

			(file_exists($What))
			=> Common\Datastore::FromArray([ realpath($What) ]),

			default
			=> NULL
		};

		if(!$Files)
		throw new Common\Error\RequiredDataMissing('Files', 'array<string>');

		// filter out files by ext type.

		$Files->Filter(function(string $F) use($Exts) {
			$Found = $Exts->Distill(
				fn(string $Ext)
				=> str_ends_with(strtolower($F), strtolower($Ext))
			);

			return $Found->Count() > 0;
		});

		$Files->Sort();

		return $Files;
	}

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
				$this->Format('* %s...', $this->Theme::Muted),
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
	PrintFilesReportBegin(Common\Datastore $Files):
	void {

		$this->PrintStatus(sprintf(
			'Checking codecs on %d %s',
			$Files->Count(),
			Common\Values::IfOneElse($Files->Count(), 'file', 'files')
		));

		return;
	}

	protected function
	PrintFilesReportEnd(Common\Datastore $Report, Common\Datastore $Codecs, Common\Datastore $Encoders, bool $FFProbe):
	void {

		$Report->Each(function(VideoInfo $Row) use($Codecs, $Encoders, $FFProbe) {

			$KeepCodec = $Codecs->Distill(fn(string $C)=> $Row->IsCodec($C));
			$KeepEnc = $Encoders->Distill(fn(string $E)=> $Row->IsEncoderLike($E));

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

				default
				=> $this->Format($Row->GetCodec(), $this->Theme::OK)
			};

			$MsgEncoder = match(TRUE) {
				(!$Row->IsEncoderGood())
				=> $this->Format($Row->GetEncoderClean(), $this->Theme::Warning),

				default
				=> $this->Format($Row->GetEncoderClean(), $this->Theme::OK)
			};

			$MsgFFProbe = $this->Format($Row->GetFFProbe(), $this->Theme::Muted);

			$MsgFilesize = sprintf(
				'%s, %s MiB/s',
				$Row->GetFilesizeClean(),
				$Row->GetSizeRate()
			);

			if($Row->GetSizeRate() > 1.0)
			$MsgFilesize = $this->Format($MsgFilesize, $this->Theme::Warning);
			else
			$MsgFilesize = $this->Format($MsgFilesize, $this->Theme::OK);

			////////

			$this->PrintLn(sprintf(
				'%s [%s, %s] [%s]',
				$MsgFile,
				$MsgCodec, $MsgEncoder,
				$MsgFilesize
			));

			if($FFProbe)
			$this->PrintLn($MsgFFProbe, 2);

			return;
		});

		$this->PrintLn();

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

			$this->PrintLn(sprintf(
				'%s %s%s%s',
				$this->Format('>>', $this->Theme::Warning),
				$Folder,
				$this->GetOption('BDS'),
				$Row->GetFileBasename()
			));

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

