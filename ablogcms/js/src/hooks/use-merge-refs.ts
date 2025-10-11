import { useMemo } from 'react';
import { handleRef, type ReactRef } from '../utils/react';

export default function useMergeRefs<T>(...refs: (ReactRef<T> | undefined)[]) {
  return useMemo(() => {
    return handleRef(...refs);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, refs);
}
