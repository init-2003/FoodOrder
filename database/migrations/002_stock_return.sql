-- Keep the deduction ledger for web orders; stock is not restored on bill cancellation.
IF OBJECT_ID(N'dbo.Web_Stock_Deductions') IS NOT NULL
    THROW 50001, 'Web_Stock_Deductions already exists; inspect before installation.', 1;
CREATE TABLE dbo.Web_Stock_Deductions (
    ID bigint IDENTITY PRIMARY KEY,
    Branch_ID nvarchar(3) NOT NULL,
    Fn_Doc_No nvarchar(15) NOT NULL,
    Trade_ID nvarchar(35) NOT NULL,
    Qty float NOT NULL,
    Is_Reversed bit NOT NULL DEFAULT 0
);
CREATE INDEX IX_Web_Stock_Deductions_Bill ON dbo.Web_Stock_Deductions (Branch_ID, Fn_Doc_No, Is_Reversed);
