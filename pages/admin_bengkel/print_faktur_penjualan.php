<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faktur Penjualan</title>
<style>
    body {
        font-family: "Poppins", Arial, sans-serif;
        background-color: #f8f9fb;
        color: #333;
        margin: 0;
        padding: 0;
    }

    .invoice-box {
        margin: 0 auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 0 15px rgba(0,0,0,0.1);
        padding: 30px 40px;
    }

    .invoice-header {
        display: flex;
        justify-content: space-between;
        border-bottom: 2px solid #ccc;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }

    .invoice-header h2 {
        color: #000;
        margin: 0;
    }

    .company-info {
        text-align: right;
        font-size: 14px;
    }

    .invoice-details {
        margin-bottom: 20px;
        font-size: 14px;
    }

    .invoice-details table {
        width: 100%;
    }

    .invoice-details td {
        padding: 0;
    }

    table {
        border-collapse: collapse;
        width: 100%;
    }

    table th {
        background: #ccc;
        color: #000;
        text-align: left;
        padding: 10px;
        font-size: 14px;
    }

    table td {
        border-bottom: 1px solid #ddd;
        padding: 5px;
        font-size: 14px;
    }

    .text-right {
        text-align: right;
    }

    .invoice-total {
        margin-top: 20px;
        width: 100%;
        border-top: 2px solid #ccc;
    }

    .invoice-total td {
        padding: 8px;
        font-size: 15px;
    }

    .invoice-footer {
        text-align: center;
        font-size: 12px;
        color: #555;
        margin-top: 40px;
    }

    @media print {
        body {
            background: #fff;
        }
        .invoice-box {
            box-shadow: none;
            margin: 0;
        }
        .no-print {
            display: none;
        }
    }
</style>
</head>
<body>

<div class="invoice-box" id="printArea">
    <div class="invoice-header">
        <div>
            <h2>FAKTUR PENJUALAN</h2>
            <p>No: <strong id="noFaktur">INV-00123</strong></p>
        </div>
        <div class="company-info">
            <strong>RC MOTOR</strong><br>
            Jl. Raya Tasikmalaya No. 45<br>
            Telp: 0812-3456-7890<br>
            Email: rcmotor@example.com
        </div>
    </div>

    <div class="invoice-details">
        <table>
            <tr>
                <td><strong>Tanggal</strong></td>
                <td>: <span id="tanggalFaktur">17 Oktober 2025</span></td>
                <td><strong>Sales</strong></td>
                <td>: <span id="kasirFaktur">Dede Hilman</span></td>
            </tr>
            <tr>
                <td><strong>Pelanggan </strong></td>
                <td>: <span id="pelangganFaktur">Bpk. Andi</span></td>
                <td><strong>Tanggal JTT </strong></td>
                <td>: <span id="tanggalJtt">18-11-2025</span></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th>Qty</th>
                <th>Satuan</th>
                <th>Harga</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody id="tbodyBarang">
            <tr>
                <td>SP001</td>
                <td>Oli Yamalube</td>
                <td>2</td>
                <td>PCS</td>
                <td>Rp 50.000</td>
                <td class="text-right">Rp 100.000</td>
            </tr>
            <tr>
                <td>SP002</td>
                <td>Busi NGK</td>
                <td>1</td>
                <td>PCS</td>
                <td>Rp 35.000</td>
                <td class="text-right">Rp 35.000</td>
            </tr>
        </tbody>
    </table>

    <table class="invoice-total">
        <tr>
            <td class="text-right"><strong>Subtotal:</strong></td>
            <td class="text-right" width="150">Rp 135.000</td>
        </tr>
        <tr>
            <td class="text-right"><strong>Diskon:</strong></td>
            <td class="text-right">Rp 0 (0%)</td>
        </tr>
        <tr>
            <td class="text-right"><strong>PPN:</strong></td>
            <td class="text-right">Rp 0</td>
        </tr>
        <tr>
            <td class="text-right"><strong>Grand Total:</strong></td>
            <td class="text-right"><strong>Rp 135.000</strong></td>
        </tr>
        <tr>
            <td class="text-right"><strong>Dibayar:</strong></td>
            <td class="text-right"><strong>Rp 135.000</strong></td>
        </tr>
        <tr>
            <td class="text-right"><strong>Kembali:</strong></td>
            <td class="text-right"><strong>Rp 0</strong></td>
        </tr>
    </table>

    <div class="invoice-footer">
        <table>
            <tr>
                <td>Penerima</td>
                <td width="50%"></td>
                <td>Hormat Kami</td>
            </tr>
            <tr>
                <td>&nbsp;&nbsp;</td>
                <td>&nbsp;&nbsp;</td>
                <td>&nbsp;&nbsp;</td>
            </tr>
            <tr>
                <td style="border-bottom:1px dotted;"></td>
                <td></td>
                <td style="border-bottom:1px dotted;"></td>
            </tr>
        </table>
        <p>Terima kasih atas kepercayaan Anda!</p>
    </div>
</div>

<div class="no-print" style="text-align:center; margin-top:20px;">
    <button onclick="window.print()" style="padding:10px 20px; background:#09f; color:#fff; border:none; border-radius:5px; cursor:pointer;">
        <i class="fa fa-print"></i> Print Faktur
    </button>
</div>

</body>
</html>
