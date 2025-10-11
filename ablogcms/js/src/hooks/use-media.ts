import { useState, useCallback, useRef } from 'react';

export type ExtendedFile =
  | {
      file: File;
      filetype: 'image';
      preview: string;
    }
  | {
      file: File;
      filetype: 'file';
    };

export type MediaDropAreaState = {
  isInsertModalOpen: boolean;
  files: File[];
  tab: 'upload' | 'select';
};

export const useMediaSelect = () => {
  const [dropAreaState, setDropAreaState] = useState<MediaDropAreaState>({
    isInsertModalOpen: false,
    files: [],
    tab: 'upload',
  });

  const uploadFile = useCallback((files: File[]) => {
    setDropAreaState((prevState) => ({
      ...prevState,
      isInsertModalOpen: true,
      files,
      tab: 'upload',
    }));
  }, []);

  const handleModalClose = () => {
    setDropAreaState((prevState) => ({
      ...prevState,
      isInsertModalOpen: false,
    }));
  };

  const handleSelectClick = useCallback(() => {
    setDropAreaState((prevState) => ({
      ...prevState,
      isInsertModalOpen: true,
      files: [],
      tab: 'select',
    }));
  }, []);

  return {
    isInsertModalOpen: dropAreaState.isInsertModalOpen,
    files: dropAreaState.files,
    tab: dropAreaState.tab,
    uploadFile,
    handleModalClose,
    handleSelectClick,
  };
};

export const useFileUpload = ({ uploader }: { uploader: (files: File[]) => void }) => {
  const fileInputRef = useRef<HTMLInputElement>(null);
  const handleUploadClick = useCallback(() => {
    fileInputRef.current?.click();
  }, []);
  const handleUploadFile = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { files } = e.target;
    if (files && files instanceof FileList) {
      uploader(Array.from(files));
    }
  };

  return { fileInputRef, handleUploadClick, handleUploadFile };
};

export const useDropZone = ({ uploader }: { uploader: (files: File[]) => void }) => {
  const onDrop = (files: ExtendedFile[]) => {
    uploader(files.map((item) => item.file));
  };
  return { onDrop };
};
