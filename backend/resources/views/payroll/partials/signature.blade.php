<div class="signature-section">
    <div class="signature-acknowledgment">
        "I hereby acknowledge that the computation and total of my salary stated above for the given period is correct."
    </div>

    <table class="signature-table">
        <tbody>
            <tr>
                <td>
                    <div class="signature-title">PREPARED BY:</div>
                    <div class="signature-name">{{ $companyInfo->sig_prepared_by ?? 'MERCIEL LAVASAN' }}</div>
                </td>
                <td>
                    <div class="signature-title">CHECKED AND VERIFIED BY:</div>
                    <div class="signature-name">{{ $companyInfo->sig_checked_by ?? 'SAIRAH JENITA' }}</div>
                </td>
                <td>
                    <div class="signature-title">RECOMMENDED BY:</div>
                    <div class="signature-name">{{ $companyInfo->sig_recommended_by ?? 'ENGR. FRANCIS GIOVANNI C. RIVERA' }}</div>
                </td>
                <td>
                    <div class="signature-title">APPROVED BY:</div>
                    <div class="signature-name">{{ $companyInfo->sig_approved_by ?? 'ENGR. OSTRIC R. RIVERA JR.' }}</div>
                    <div class="signature-position">{{ $companyInfo->sig_approved_by_position ?? 'Proprietor/Manager' }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="4" style="height: 2px; border: none; padding: 0;"></td>
            </tr>
            <tr>
                <td>
                    <div class="signature-name">{{ $companyInfo->sig_prepared_by_2 ?? 'JAMAICA CRISTEL MAE SUGABO' }}</div>
                </td>
                <td>
                    <div class="signature-name">{{ $companyInfo->sig_checked_by_2 ?? 'ENGR. ELISA MAY PARCON' }}</div>
                </td>
                <td>
                    <div class="signature-name">{{ $companyInfo->sig_recommended_by_2 ?? 'ENGR. OSTRIC C. RIVERA, III' }}</div>
                </td>
                <td>
                    <div class="signature-name">{{ $companyInfo->sig_approved_by_2 ?? '' }}</div>
                    <div class="signature-position">{{ $companyInfo->sig_approved_by_position_2 ?? '' }}</div>
                </td>
            </tr>
        </tbody>
    </table>
</div>