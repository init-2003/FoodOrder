-- New deductions only: historical deductions do not record every Group_Pro row.
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
EXEC(N'CREATE TRIGGER dbo.TR_Web_Return_Stock_On_Cancel ON dbo.Fnt_Header AFTER UPDATE AS
BEGIN
 SET NOCOUNT ON;
 IF NOT UPDATE(Doc_Sts) RETURN;
 DECLARE @restore TABLE (Branch_ID nvarchar(3), Fn_Doc_No nvarchar(15), Trade_ID nvarchar(35), Qty float);
 UPDATE w SET Is_Reversed=1
 OUTPUT inserted.Branch_ID, inserted.Fn_Doc_No, inserted.Trade_ID, inserted.Qty INTO @restore
 FROM dbo.Web_Stock_Deductions w
 JOIN inserted i ON i.Branch_ID=w.Branch_ID AND i.Fn_Doc_No=w.Fn_Doc_No
 JOIN deleted d ON d.Branch_ID=i.Branch_ID AND d.Fn_Doc_No=i.Fn_Doc_No
 WHERE i.Doc_Sts=3 AND ISNULL(d.Doc_Sts,-1)<>3 AND w.Is_Reversed=0;
 IF EXISTS (SELECT 1 FROM @restore r LEFT JOIN dbo.Trade t ON t.Branch_ID=r.Branch_ID AND t.Trade_ID=r.Trade_ID WHERE t.Trade_ID IS NULL)
     THROW 50002, ''Cannot restore stock: a product is missing.'', 1;
 DECLARE @balances TABLE (Branch_ID nvarchar(3), Trade_ID nvarchar(35), Balance float, Cost money, Sale money);
 UPDATE t SET Qty=t.Qty+x.Qty
 OUTPUT inserted.Branch_ID,inserted.Trade_ID,inserted.Qty,inserted.Cost_Price,inserted.Sale_Price INTO @balances
 FROM dbo.Trade t JOIN (SELECT Branch_ID,Trade_ID,SUM(Qty) Qty FROM @restore GROUP BY Branch_ID,Trade_ID) x
 ON t.Branch_ID=x.Branch_ID AND t.Trade_ID=x.Trade_ID;
 INSERT INTO dbo.Movement (Branch_ID,Mo_Doc_Date,Mo_Doc_No,Reference,Trade_ID,Get_Qty,Cost_Price,Sale_Price,Balance_Qty,Pb_User,Pb_Now)
 SELECT r.Branch_ID,GETDATE(),r.Fn_Doc_No,N''คืนสต็อก-web'',r.Trade_ID,SUM(r.Qty),b.Cost,b.Sale,b.Balance,N''web'',GETDATE()
 FROM @restore r JOIN @balances b ON b.Branch_ID=r.Branch_ID AND b.Trade_ID=r.Trade_ID
 GROUP BY r.Branch_ID,r.Fn_Doc_No,r.Trade_ID,b.Cost,b.Sale,b.Balance;
END');
