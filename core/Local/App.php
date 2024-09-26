<?php

namespace Local;

use Nether\Common;
use Nether\Console;

#[Console\Meta\Application('vidtool', '1.0.0-dev', Phar: 'vidtool.phar')]
class App
extends Console\Client {

	#[Console\Meta\Command('codec')]
	#[Console\Meta\Arg('file/folder')]
	#[Console\Meta\Error(1, 'not found: %s')]
	public function
	HandleCheckCodecs():
	int {

		$What = realpath($this->GetInput(1) ?? '.');
		$OptCodecMove = $this->GetOption('move');
		$OptShowProbe = $this->GetOption('probe');
		$OptMoveMode = 1;

		$GoodCodecs = new Common\Datastore([ 'HEVC' ]);
		$GoodEncoders = new COmmon\Datastore([ 'HandBrake' ]);
		$CheckExts = new Common\Datastore([ 'mp4' ]);

		$Files = NULL;
		$File = NULL;
		$Cmd = NULL;
		$Row = NULL;
		$Found = NULL;
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

		// fetch file info.

		$this->PrintStatus(sprintf(
			'Checking codecs on %d %s',
			$Files->Count(),
			Common\Values::IfOneElse($Files->Count(), 'file', 'files')
		));

		foreach($Files as $File) {
			$Cmd = Console\Struct\CommandLineUtil::Exec(sprintf(
				'ffprobe %s 2>&1',
				escapeshellarg($File)
			));

			$Row = VideoInfo::FromFile($File);
			$Row->SetError($Cmd->Error);
			$Row->DigestFFProbe($Cmd->GetOutputString());

			$Report->Shove($Row->GetFile(), $Row);
		}

		// print a report on all the files found.

		$Report->Each(function(VideoInfo $Row) use($GoodCodecs, $GoodEncoders, $OptShowProbe) {

			$KeepCodec = $GoodCodecs->Distill(fn(string $E)=> $E === $Row->GetCodec());
			$KeepEnc = $GoodEncoders->Distill(fn(string $E)=> str_starts_with($Row->GetEncoder(), $E));

			if($KeepCodec->Count() === 0)
			$Row->SetStatus($Row::StatusWrongCodec);

			elseif($KeepEnc->Count() === 0)
			$Row->SetStatus($Row::StatusWrongEncoder);

			////////

			$MsgCodec = match(TRUE) {
				(!$Row->IsCodecGood())
				=> $this->Format($Row->GetCodec(), $this->Theme::Error),

				(!$Row->IsEncoderGood())
				=> $this->Format($Row->GetCodec(), $this->Theme::Warning),

				default
				=> $this->Format($Row->GetCodec(), $this->Theme::OK)
			};

			$this->PrintLn(sprintf(
				'[%s] %s (%s)',
				$MsgCodec,
				$Row->GetFileBasename(),
				$Row->GetEncoder()
			));

			if($OptShowProbe)
			$this->PrintLn($Row->GetFFProbe(), 2);

			return;
		});

		if($OptCodecMove) {
			$Report->Each(function(VideoInfo $Row) use($GoodCodecs, $OptMoveMode) {

				if($Row->GetStatus() === $Row::StatusOK)
				return;

				////////

				$Folder = 'Todo';

				if($OptMoveMode === 2)
				$Folder = $Row->GetCodec();

				if($OptMoveMode === 3)
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
		}

		return 0;
	}

};

