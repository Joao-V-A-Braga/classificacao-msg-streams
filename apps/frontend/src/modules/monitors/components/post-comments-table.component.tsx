'use client';

import { EmptyListState } from '@/shared/components/ui/empty-list-state';
import { PaginationControls } from '@/shared/components/ui/pagination-controls';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/shared/components/ui/table';
import { TableCard } from '@/shared/components/ui/table-card';
import { formatDateTime } from '../lib/monitor-display.util';
import type { MonitorMessage, MonitorTag } from '../types/monitor.type';
import { LayaTranslation } from './laya-translation.component';
import { MessageLabelBadge } from './message-label-badge.component';

type PostCommentsTableProps = {
  messages: MonitorMessage[];
  tags: MonitorTag[];
  page: number;
  total: number;
  perPage: number;
  isLoading: boolean;
  onPageChange: (page: number) => void;
};

/** Comentários de um post: tabela paginada, os mais novos primeiro (novos entram no topo da página 1). */
export function PostCommentsTable({ messages, tags, page, total, perPage, isLoading, onPageChange }: PostCommentsTableProps) {
  const totalPages = Math.max(1, Math.ceil(total / perPage));

  return (
    <div className="space-y-4">
      <TableCard>
        {!isLoading && messages.length === 0 ? (
          <div className="py-10">
            <EmptyListState
              title="Nenhum comentário ainda"
              subtitle="Com o monitoramento ativo, os comentários novos do post aparecem aqui já classificados."
            />
          </div>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Autor</TableHead>
                <TableHead>Comentário</TableHead>
                <TableHead>Tag (Laya)</TableHead>
                <TableHead>Recebido em</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {messages.map((message) => (
                <TableRow key={message.id}>
                  <TableCell className="font-medium">{message.authorName}</TableCell>
                  <TableCell className="max-w-md whitespace-normal break-words">
                    {message.text}
                    <LayaTranslation text={message.translatedText} className="mt-1" />
                  </TableCell>
                  <TableCell>
                    <MessageLabelBadge message={message} tags={tags} />
                  </TableCell>
                  <TableCell className="whitespace-nowrap">{formatDateTime(message.receivedAt)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </TableCard>

      {total > 0 ? (
        <PaginationControls
          page={page}
          totalPages={totalPages}
          totalItems={total}
          totalLabel="comentários"
          onPageChange={onPageChange}
          disabled={isLoading}
        />
      ) : null}
    </div>
  );
}
