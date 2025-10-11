import { useState, useCallback } from 'react';
import { Node as TiptapNode } from '@tiptap/pm/model';

export const useMediaUpdate = ({ inspectMedia }: { inspectMedia: () => TiptapNode | undefined }) => {
  const [isUpdateModalOpen, setIsUpdateModalOpen] = useState(false);
  const targetMediaId = inspectMedia()?.attrs?.mediaId || null;
  const imageSrc = inspectMedia()?.attrs?.src || null;
  let selfImgSrc = null;

  if (imageSrc && !/^(?:[a-z]+:)?\/\//i.test(imageSrc)) {
    selfImgSrc = imageSrc;
  }
  const handleUpdateModalClose = useCallback(() => {
    setIsUpdateModalOpen(false);
  }, []);

  const handleUpdateClick = useCallback(() => {
    setIsUpdateModalOpen(true);
  }, []);

  return {
    targetMediaId,
    selfImgSrc,
    isUpdateModalOpen,
    handleUpdateModalClose,
    handleUpdateClick,
  };
};
