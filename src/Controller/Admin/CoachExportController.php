<?php

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/coaches')]
#[IsGranted('ROLE_ADMIN')]
class CoachExportController extends AbstractController
{
    #[Route('/export', name: 'admin_coaches_export')]
    public function export(UserRepository $userRepository): Response
    {
        // Get all coaches
        $coaches = $userRepository->findUsersByRole('ROLE_COACH');
        
        // Create new Spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Coaches List');
        
        // Set header row
        $headers = [
            'A1' => 'ID',
            'B1' => 'Full Name',
            'C1' => 'Username',
            'D1' => 'Email',
            'E1' => 'Status',
            'F1' => 'Bio',
            'G1' => 'Teams Joined',
            'H1' => 'Teams Owned',
            'I1' => 'Member Since',
            'J1' => 'Last Login',
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Style header row
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '667EEA'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(10);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(30);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(40);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(15);
        $sheet->getColumnDimension('I')->setWidth(20);
        $sheet->getColumnDimension('J')->setWidth(20);
        
        // Fill data
        $row = 2;
        foreach ($coaches as $coach) {
            $sheet->setCellValue('A' . $row, $coach->getId());
            $sheet->setCellValue('B' . $row, $coach->getFullName() ?? $coach->getUsername());
            $sheet->setCellValue('C' . $row, $coach->getUsername());
            $sheet->setCellValue('D' . $row, $coach->getEmail());
            $sheet->setCellValue('E' . $row, $coach->getAccountStatus()->getLabel());
            $sheet->setCellValue('F' . $row, $coach->getBio() ?? 'N/A');
            $sheet->setCellValue('G' . $row, count($coach->getTeamMemberships()));
            $sheet->setCellValue('H' . $row, count($coach->getOwnedTeams()));
            $sheet->setCellValue('I' . $row, $coach->getCreatedAt()->format('Y-m-d H:i:s'));
            $sheet->setCellValue('J' . $row, $coach->getLastLogin() ? $coach->getLastLogin()->format('Y-m-d H:i:s') : 'Never');
            
            // Apply row styling
            $rowStyle = [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            
            $sheet->getStyle('A' . $row . ':J' . $row)->applyFromArray($rowStyle);
            
            // Color code status column
            $statusColor = match($coach->getAccountStatus()->value) {
                'ACTIVE' => '43E97B',
                'SUSPENDED' => 'FFA751',
                'BANNED' => 'FF6B6B',
                default => 'CCCCCC',
            };
            
            $sheet->getStyle('E' . $row)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $statusColor],
                ],
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                ],
            ]);
            
            // Wrap text for Bio column
            $sheet->getStyle('F' . $row)->getAlignment()->setWrapText(true);
            
            $row++;
        }
        
        // Add summary row
        $summaryRow = $row + 1;
        $sheet->setCellValue('A' . $summaryRow, 'TOTAL COACHES:');
        $sheet->setCellValue('B' . $summaryRow, count($coaches));
        
        $sheet->getStyle('A' . $summaryRow . ':B' . $summaryRow)->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E8F5E9'],
            ],
        ]);
        
        // Active coaches count
        $activeCoaches = array_filter($coaches, fn($c) => $c->getAccountStatus()->value === 'ACTIVE');
        $sheet->setCellValue('A' . ($summaryRow + 1), 'ACTIVE COACHES:');
        $sheet->setCellValue('B' . ($summaryRow + 1), count($activeCoaches));
        
        // Generate filename with timestamp
        $filename = 'coaches_export_' . date('Y-m-d_His') . '.xlsx';
        
        // Create writer and response
        $writer = new Xlsx($spreadsheet);
        
        $response = new StreamedResponse(function() use ($writer) {
            $writer->save('php://output');
        });
        
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        
        return $response;
    }
}
