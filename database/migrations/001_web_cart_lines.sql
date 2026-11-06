-- The mapping is separate from Fnt_Detail so existing POS rows with blank
-- Food_Que_STS are never treated as web carts. No FK is added to POS tables.
IF OBJECT_ID(N'dbo.Web_Cart_Lines') IS NOT NULL
    THROW 50001, 'dbo.Web_Cart_Lines already exists; inspect before migration.', 1;
BEGIN
    CREATE TABLE dbo.Web_Cart_Lines (
        Detail_ID numeric(18,0) NOT NULL PRIMARY KEY,
        Branch_ID varchar(3) NOT NULL,
        Fn_Doc_No nvarchar(15) NOT NULL,
        Session_Token varchar(128) NOT NULL,
        Cart_Key varchar(128) NOT NULL,
        Note nvarchar(200) NOT NULL CONSTRAINT DF_Web_Cart_Lines_Note DEFAULT N'',
        State char(1) NOT NULL CONSTRAINT DF_Web_Cart_Lines_State DEFAULT 'D',
        Revision int NOT NULL CONSTRAINT DF_Web_Cart_Lines_Revision DEFAULT 1,
        Last_Operation varchar(128) NOT NULL,
        Last_Operation_Hash char(64) NOT NULL,
        Order_Key varchar(128) NULL,
        Updated_At datetime2 NOT NULL CONSTRAINT DF_Web_Cart_Lines_Updated DEFAULT SYSUTCDATETIME(),
        CONSTRAINT CK_Web_Cart_Lines_State CHECK (State IN ('D', 'S', 'X')),
        CONSTRAINT UQ_Web_Cart_Lines_Owner UNIQUE (Branch_ID, Fn_Doc_No, Session_Token, Cart_Key)
    );
END;
